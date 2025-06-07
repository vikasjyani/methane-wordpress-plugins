"""
API routes for PyPSA Co-optimisation Modeling module features.

This blueprint handles API endpoints for validating PyPSA model configurations,
initiating simulation runs, and fetching related data like available load profiles.
Job statuses for PyPSA runs are handled by a generic job status endpoint in the main app.
It uses `current_app.config` for storing simulated job information and accessing
other configurations like available load profiles. It now also includes an endpoint
for uploading and parsing a PyPSA input template.
"""
from flask import Blueprint, request, jsonify, current_app
import time
import random
import datetime
import uuid # For unique job IDs
import os
from werkzeug.utils import secure_filename

# Import the parser function
from .file_parser import parse_pypsa_input_template
import pandas as pd # For DataFrame checks

# Blueprint for PyPSA modeling API, prefixed with /api/pypsa_model
pypsa_modeling_bp = Blueprint('pypsa_modeling_api', __name__, url_prefix='/api/pypsa_model')

@pypsa_modeling_bp.route('/upload_pypsa_template', methods=['POST'])
def api_upload_pypsa_template():
    """
    API endpoint to upload, parse, and process a pypsa_input_template.xlsx file.
    The uploaded file is temporarily saved, parsed using `parse_pypsa_input_template`,
    and the extracted data is stored in `current_app.config['PROCESSED_PYPSA_DATA']`.

    Expects a 'pypsa_template_file' in `request.files`.

    Returns:
        JSON: Success status, message, and summary of processed data.
    """
    if 'pypsa_template_file' not in request.files:
        return jsonify({"success": False, "message": "No 'pypsa_template_file' part in the request."}), 400

    file = request.files['pypsa_template_file']
    if file.filename == '':
        return jsonify({"success": False, "message": "No file selected for uploading."}), 400

    if file:
        filename = secure_filename(file.filename)
        upload_folder = current_app.config.get('UPLOAD_FOLDER')
        if not upload_folder:
            current_app.logger.error("UPLOAD_FOLDER not configured in Flask app.")
            return jsonify({"success": False, "message": "File upload path not configured on server."}), 500

        temp_file_path = os.path.join(upload_folder, f"temp_pypsa_{filename}")

        try:
            file.save(temp_file_path)
            current_app.logger.info(f"PyPSA template '{filename}' saved temporarily to '{temp_file_path}'.")

            parsed_data = parse_pypsa_input_template(temp_file_path)

            if parsed_data is None: # Parser returns None on critical file read errors
                current_app.logger.error(f"Parsing failed critically for PyPSA template '{filename}'.")
                return jsonify({"success": False, "message": f"Failed to parse the PyPSA template '{filename}'. Ensure file is valid Excel and not corrupted."}), 500

            current_app.config['PROCESSED_PYPSA_DATA'] = parsed_data
            current_app.logger.info(f"Successfully parsed and stored data from PyPSA template '{filename}'.")

            try:
                os.remove(temp_file_path)
                current_app.logger.info(f"Temporary PyPSA template file '{temp_file_path}' deleted.")
            except Exception as e_remove:
                current_app.logger.warning(f"Could not remove temporary PyPSA template file '{temp_file_path}': {e_remove}")

            # Prepare settings summary, handling potential None or non-DataFrame values
            settings_summary = {}
            pypsa_settings = parsed_data.get('Settings')
            if isinstance(pypsa_settings, dict):
                main_settings_df = pypsa_settings.get('Scenario_Info') # Assuming marker was ~Scenario_Info
                if isinstance(main_settings_df, pd.DataFrame) and not main_settings_df.empty:
                    # Convert DataFrame to dict for JSON response; assumes simple key-value structure for summary
                    try:
                        settings_summary = main_settings_df.set_index(main_settings_df.columns[0])[main_settings_df.columns[1]].to_dict()
                    except Exception: # Handle cases where conversion to dict might fail
                         settings_summary = {"info": f"{len(main_settings_df)} settings rows found"}


            return jsonify({
                "success": True,
                "message": f"PyPSA input template '{filename}' processed successfully.",
                "data": {
                    "filename": filename,
                    "sheets_parsed": list(parsed_data.keys()),
                    "settings_summary": settings_summary, # e.g. content of Settings -> Scenario_Info table
                    "components_found": [s for s in ['Buses', 'Generators', 'Lines'] if s in parsed_data and not parsed_data[s].empty]
                }
            }), 200

        except Exception as e:
            current_app.logger.error(f"Error during PyPSA template processing for '{filename}': {str(e)}", exc_info=True)
            if os.path.exists(temp_file_path):
                try: os.remove(temp_file_path)
                except Exception as e_clean: current_app.logger.error(f"Error cleaning up temp PyPSA file '{temp_file_path}': {e_clean}")
            return jsonify({"success": False, "message": f"An unexpected error occurred processing the PyPSA template: {str(e)}"}), 500
    else: # Should not be reached due to earlier checks
        return jsonify({"success": False, "message": "Invalid file provided for PyPSA template."}), 400


@pypsa_modeling_bp.route('/validate_model_setup', methods=['POST']) # Renamed from validate_configuration
def api_validate_pypsa_model_setup(): # Renamed
    """
    API endpoint to validate the currently loaded PyPSA model setup (from processed template).
    It checks `current_app.config['PROCESSED_PYPSA_DATA']`.

    Returns:
        JSON: Success status, message, and detailed validation results.
    """
    config_data_from_ui = request.json # This might contain override settings from UI
    parsed_pypsa_data = current_app.config.get('PROCESSED_PYPSA_DATA')

    if not parsed_pypsa_data:
        return jsonify({
            "success": False,
            "message": "No PyPSA input template has been uploaded and processed yet. Please upload a template first.",
            "data": {"all_ok": False, "validation_details": {"template_uploaded": False}}
        }), 400

    scenario_name = config_data_from_ui.get('scenarioName', 'Unnamed PyPSA Scenario') # Get name from UI if available
    print(f"Validating PyPSA model setup for scenario: {scenario_name} using processed template data.")

    validation_details = {}
    issues_found = []

    # Check 1: Settings sheet and key tables
    settings_data = parsed_pypsa_data.get('Settings')
    if not isinstance(settings_data, dict) or not settings_data:
        issues_found.append("Critical: 'Settings' sheet data is missing or not parsed correctly.")
        validation_details["settings_sheet_ok"] = False
    else:
        validation_details["settings_sheet_ok"] = True
        # Example: Check for a specific settings table, e.g., 'Scenario_Info'
        if not isinstance(settings_data.get('Scenario_Info'), pd.DataFrame) or settings_data.get('Scenario_Info').empty:
            issues_found.append("Warning: 'Scenario_Info' table missing or empty in Settings sheet.")
            validation_details["scenario_info_table_ok"] = False
        else:
            validation_details["scenario_info_table_ok"] = True

    # Check 2: Essential component sheets
    essential_components = ['Buses', 'Generators', 'Lines'] # Could also include Links, Transformers
    for component in essential_components:
        component_df = parsed_pypsa_data.get(component)
        if not isinstance(component_df, pd.DataFrame) or component_df.empty:
            issues_found.append(f"Critical: Component sheet '{component}' is missing or empty.")
            validation_details[f"{component.lower()}_sheet_ok"] = False
        else:
            validation_details[f"{component.lower()}_sheet_ok"] = True
            # Further checks: e.g., if 'bus' column exists in Generators, if 'name' is unique in Buses
            if component == 'Buses' and 'name' not in component_df.columns:
                 issues_found.append(f"Error: 'Buses' sheet missing 'name' column.")
                 validation_details[f"{component.lower()}_sheet_ok"] = False


    # Check 3: Time-series data (e.g., Demand/Load)
    demand_df = parsed_pypsa_data.get('Demand', parsed_pypsa_data.get('Load')) # Check for common names
    if not isinstance(demand_df, pd.DataFrame) or demand_df.empty:
        issues_found.append("Warning: 'Demand' (or 'Load') time-series sheet is missing or empty.")
        validation_details["demand_data_ok"] = False
    else:
        validation_details["demand_data_ok"] = True
        if not pd.api.types.is_datetime64_any_dtype(demand_df.index):
             issues_found.append("Error: 'Demand' (or 'Load') sheet index is not datetime. Ensure first column is timestamps.")
             validation_details["demand_data_ok"] = False


    all_ok = not any("Critical" in issue for issue in issues_found) # Basic check: only critical issues make it not "ok"
    message = f"PyPSA model setup validation for '{scenario_name}' "
    message += "completed." if not issues_found else f"completed with {len(issues_found)} issue(s)."

    return jsonify({
        "success": True,
        "message": message,
        "data": {
            "scenario_name": scenario_name,
            "validation_passed_critical": all_ok, # True if no critical issues
            "validation_details": validation_details, # Status of individual checks
            "issues_list": issues_found, # List of descriptive issues
            "validated_at": datetime.datetime.now().isoformat()
        }
    }), 200


@pypsa_modeling_bp.route('/run_simulation', methods=['POST'])
def api_run_pypsa_simulation():
    """API endpoint to initiate a new PyPSA co-optimisation simulation run."""
    config_data = request.json
    scenario_name = config_data.get('scenarioName')
    if not scenario_name:
        return jsonify({"success": False, "message": "Scenario name is required."}), 400

    # Check if PyPSA data has been uploaded and processed
    processed_pypsa_data = current_app.config.get('PROCESSED_PYPSA_DATA')
    if not processed_pypsa_data:
        return jsonify({"success": False, "message": "PyPSA input template not processed. Please upload and process a template first."}), 400

    # Potentially use some elements from config_data (UI overrides) and processed_pypsa_data (template data)
    # For now, just log that processed data is available.
    print(f"Initiating PyPSA run for '{scenario_name}'. Processed template data is available with {len(processed_pypsa_data)} sheets/tables.")

    job_id = f"pypsa_{str(uuid.uuid4())[:8]}"
    simulated_jobs = current_app.config.get('SIMULATED_JOBS', {})
    simulated_jobs[job_id] = {
        "type": "pypsa", "status": "queued", "progress": 0.0, "start_time": time.time(),
        "config": config_data, "scenario_name": scenario_name,
        "user": request.headers.get("X-User-ID", "sim_user_pypsa")
    }
    current_app.config['SIMULATED_JOBS'] = simulated_jobs

    return jsonify({
        "success": True, "message": f"PyPSA simulation for '{scenario_name}' initiated.",
        "job_id": job_id, "scenario_name": scenario_name
    }), 202

@pypsa_modeling_bp.route('/available_load_profiles', methods=['GET'])
def api_get_available_load_profiles_for_pypsa_config():
    """API endpoint to retrieve available load profiles for PyPSA config UI."""
    load_profiles = current_app.config.get('LOAD_PROFILES', [])
    formatted_profiles = [{"id": lp["id"], "name": lp["name"]} for lp in load_profiles]
    return jsonify({"success": True, "data": formatted_profiles, "message": "Load profiles fetched."}), 200
