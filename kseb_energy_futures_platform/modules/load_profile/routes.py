"""
API routes for Load Profile Generation module features.

This blueprint handles API endpoints related to fetching available demand scenarios
for load profile generation, previewing load profiles, initiating generation jobs,
saving generated profiles, and retrieving data for load profile analysis (heatmap,
daily patterns, LDC). It uses `current_app.config` for storing simulated job
statuses and metadata of generated load profiles and available demand scenarios.
It also now includes an endpoint for uploading and processing a load curve template.
"""
from flask import Blueprint, request, jsonify, current_app
import time
import random
import datetime
import uuid # For unique job IDs
import os
from werkzeug.utils import secure_filename

# Import the parser function
from .file_parser import process_load_curve_template
import pandas as pd # For creating empty DataFrame if needed


# Blueprint for load profile API, prefixed with /api/load_profile
load_profile_bp = Blueprint('load_profile_api', __name__, url_prefix='/api/load_profile')

@load_profile_bp.route('/upload_load_curve_template', methods=['POST'])
def api_upload_load_curve_template():
    """
    API endpoint to upload, parse, and process a load_curve_template.xlsx file.
    The uploaded file is temporarily saved, parsed using `process_load_curve_template`,
    and the extracted data is stored in `current_app.config['PROCESSED_LOAD_CURVE_DATA']`.

    Expects a 'load_curve_template_file' in `request.files`.

    Returns:
        JSON: Success status, message, and summary of processed data (filename, sheets found).
              Returns 400 if no file is provided or file is invalid.
              Returns 500 if parsing fails.
    """
    if 'load_curve_template_file' not in request.files:
        return jsonify({"success": False, "message": "No 'load_curve_template_file' part in the request."}), 400

    file = request.files['load_curve_template_file']
    if file.filename == '':
        return jsonify({"success": False, "message": "No file selected for uploading."}), 400

    if file: # Basic check for allowed extensions could be added here
        filename = secure_filename(file.filename)
        upload_folder = current_app.config.get('UPLOAD_FOLDER')
        if not upload_folder:
            current_app.logger.error("UPLOAD_FOLDER not configured in Flask app.")
            return jsonify({"success": False, "message": "File upload path not configured on server."}), 500

        temp_file_path = os.path.join(upload_folder, f"temp_lc_{filename}") # Add prefix to avoid clashes

        try:
            file.save(temp_file_path)
            current_app.logger.info(f"Load curve template '{filename}' saved temporarily to '{temp_file_path}'.")

            parsed_data = process_load_curve_template(temp_file_path)

            if parsed_data is None:
                current_app.logger.error(f"Parsing failed for load curve template '{filename}'.")
                return jsonify({"success": False, "message": f"Failed to parse the uploaded template '{filename}'. Check file format and content."}), 500

            current_app.config['PROCESSED_LOAD_CURVE_DATA'] = parsed_data
            current_app.logger.info(f"Successfully parsed and stored data from load curve template '{filename}'.")

            try:
                os.remove(temp_file_path)
                current_app.logger.info(f"Temporary load curve template file '{temp_file_path}' deleted.")
            except Exception as e_remove:
                current_app.logger.warning(f"Could not remove temporary load curve template file '{temp_file_path}': {e_remove}")

            sheets_found = [key for key, df in parsed_data.items() if df is not None and not df.empty]
            return jsonify({
                "success": True,
                "message": f"Load curve template '{filename}' processed successfully.",
                "data": {
                    "filename": filename,
                    "sheets_found": sheets_found,
                    "past_hourly_demand_records": len(parsed_data.get('past_hourly_demand', pd.DataFrame())),
                    "annual_demand_targets": len(parsed_data.get('total_annual_demand', pd.DataFrame()))
                }
            }), 200

        except Exception as e:
            current_app.logger.error(f"Error during load curve template processing for '{filename}': {e}")
            if os.path.exists(temp_file_path):
                try: os.remove(temp_file_path)
                except Exception as e_clean: current_app.logger.error(f"Error cleaning up temp file '{temp_file_path}': {e_clean}")
            return jsonify({"success": False, "message": f"An error occurred processing the template: {str(e)}"}), 500
    else:
        return jsonify({"success": False, "message": "Invalid file."}), 400


@load_profile_bp.route('/available_demand_scenarios', methods=['GET'])
def api_get_available_demand_scenarios_for_lp():
    """API endpoint to retrieve a list of available demand scenarios."""
    demand_scenarios = current_app.config.get('DEMAND_SCENARIOS', [])
    formatted_scenarios = [{"id": s["id"], "name": s["name"]} for s in demand_scenarios]
    return jsonify({
        "success": True,
        "data": formatted_scenarios,
        "message": "Available demand scenarios fetched successfully."
    }), 200

@load_profile_bp.route('/preview', methods=['POST'])
def api_preview_load_profile():
    """API endpoint to generate a preview of a load profile."""
    config = request.json
    print(f"Received request to preview load profile with config: {config}")

    processed_lc_data = current_app.config.get('PROCESSED_LOAD_CURVE_DATA')
    log_message = " (using default simulation data)."
    if processed_lc_data and not processed_lc_data.get('past_hourly_demand', pd.DataFrame()).empty:
        log_message = f" (found {len(processed_lc_data['past_hourly_demand'])} records from uploaded template)."
        # Actual preview logic would use processed_lc_data here.
        # For now, just acknowledge its presence.
    print(f"Simulating preview data {log_message}")

    base_load_factor = float(config.get('baseLoadFactor', 0.35))
    peak_load_factor = float(config.get('peakLoadFactor', 0.9))
    simulated_average_load_mw = random.uniform(2000, 4000)
    min_load_mw = simulated_average_load_mw * base_load_factor
    peak_load_mw = simulated_average_load_mw * peak_load_factor
    preview_load_values = []
    for h in range(24):
        daily_shape_factor = (0.6 * (1 - abs(h - 9) / 9)**3 + 1.0 * (1 - abs(h - 19) / 7)**3) / 1.6
        load_value = min_load_mw + (peak_load_mw - min_load_mw) * daily_shape_factor
        load_value *= random.uniform(0.95, 1.05)
        preview_load_values.append(int(load_value))
    preview_data = {"time_points": [f"{h:02d}:00" for h in range(24)], "load_values_mw": preview_load_values}

    return jsonify({
        "success": True,
        "data": preview_data,
        "message": f"Load profile preview data generated{log_message}"
    }), 200

@load_profile_bp.route('/generate', methods=['POST'])
def api_generate_load_profiles():
    """API endpoint to initiate a new load profile generation job."""
    config = request.json
    if not config or not config.get('demandScenario'):
        return jsonify({"success": False, "message": "Demand scenario ID ('demandScenario') is required."}), 400

    processed_lc_data = current_app.config.get('PROCESSED_LOAD_CURVE_DATA')
    input_data_source_log = "default internal data"
    if processed_lc_data:
        input_data_source_log = "uploaded load curve template"
        # Actual generation logic would use processed_lc_data here.

    demand_scenario_id = config.get('demandScenario')
    profile_name_base = f"LP_{demand_scenario_id}_{config.get('startYear', 'YYYY')}-{config.get('endYear', 'YYYY')}"
    profile_name = config.get('profileName', profile_name_base)

    print(f"Received request to generate load profile: {profile_name}, using {input_data_source_log}")
    job_id = f"lp_gen_{str(uuid.uuid4())[:8]}"

    simulated_jobs = current_app.config.get('SIMULATED_JOBS', {})
    simulated_jobs[job_id] = {
        "type": "load_profile_generation", "status": "queued", "progress": 0.0,
        "start_time": time.time(), "config": config, "profile_name_intended": profile_name,
        "user": request.headers.get("X-User-ID", "sim_user_lp"),
        "data_source_used": input_data_source_log # For tracking
    }
    current_app.config['SIMULATED_JOBS'] = simulated_jobs

    return jsonify({
        "success": True,
        "message": f"Load profile generation for '{profile_name}' initiated (using {input_data_source_log}).",
        "job_id": job_id, "profile_name": profile_name
    }), 201

# ... (other load profile API endpoints: /save_generated, /data/*, /list_all) ...
# These should remain largely the same as they operate on "saved" profile metadata or simulated data.
# No direct changes needed for them from this subtask's scope.

@load_profile_bp.route('/save_generated', methods=['POST'])
def api_save_generated_load_profile():
    data = request.json; job_id = data.get('job_id'); profile_name_from_job = data.get('profile_name')
    if not job_id or not profile_name_from_job: return jsonify({"success": False, "message": "Job ID and Profile Name are required."}), 400
    job_info = current_app.config.get('SIMULATED_JOBS', {}).get(job_id)
    if not job_info or job_info.get('status') != "completed": return jsonify({"success": False, "message": f"Job '{job_id}' not found or not completed."}), 404
    load_profiles_list = current_app.config.get('LOAD_PROFILES', [])
    if any(lp['id'] == job_id for lp in load_profiles_list): return jsonify({"success": False, "message": f"Profile from job '{job_id}' already saved."}), 409
    job_config = job_info.get('config', {})
    new_profile_entry = {
        "id": job_id, "name": profile_name_from_job, "demand_scenario_id": job_config.get('demandScenario'),
        "generated_at": datetime.datetime.now().isoformat(), "frequency": job_config.get('frequency', 'Hourly'),
        "unit": job_config.get('unit', 'MW'), "source_job_id": job_id,
        "simulated_data_path": f"/sim_storage/load_profiles/{job_id}.csv"
    }
    load_profiles_list.append(new_profile_entry)
    current_app.config['LOAD_PROFILES'] = load_profiles_list
    return jsonify({"success": True, "message": f"Load Profile '{profile_name_from_job}' saved.", "data": new_profile_entry}), 201

@load_profile_bp.route('/data/<profile_id>/heatmap', methods=['GET'])
def api_get_load_profile_heatmap_data(profile_id):
    if not any(lp['id'] == profile_id for lp in current_app.config.get('LOAD_PROFILES', [])): return jsonify({"success": False, "message": f"Profile '{profile_id}' not found."}), 404
    data = {"z": [[random.randint(1500,4000) for _ in range(24)] for _ in range(90)], "x": [f"{h:02d}:00" for h in range(24)], "y": [f"Day {d+1}" for d in range(90)]}
    return jsonify({"success": True, "data": data, "profile_id": profile_id, "message": "Heatmap data fetched."}), 200

@load_profile_bp.route('/data/<profile_id>/daily_pattern', methods=['GET'])
def api_get_load_profile_daily_pattern_data(profile_id):
    if not any(lp['id'] == profile_id for lp in current_app.config.get('LOAD_PROFILES', [])): return jsonify({"success": False, "message": f"Profile '{profile_id}' not found."}), 404
    data = {"time_points": [f"{h:02d}:00" for h in range(24)], "weekday_average_mw": [random.randint(1800,3800) for _ in range(24)], "weekend_average_mw": [random.randint(1600,3200) for _ in range(24)]}
    return jsonify({"success": True, "data": data, "profile_id": profile_id, "message": "Daily pattern data fetched."}), 200

@load_profile_bp.route('/data/<profile_id>/ldc', methods=['GET'])
def api_get_load_profile_ldc_data(profile_id):
    if not any(lp['id'] == profile_id for lp in current_app.config.get('LOAD_PROFILES', [])): return jsonify({"success": False, "message": f"Profile '{profile_id}' not found."}), 404
    points = sorted([max(500,min(random.gauss(2800,800),6000)) for _ in range(8760)], reverse=True)
    return jsonify({"success": True, "data": {"load_values_mw": points, "duration_percent": [(i/8760)*100 for i in range(8760)]}, "profile_id": profile_id, "message": "LDC data fetched."}), 200

@load_profile_bp.route('/list_all', methods=['GET'])
def api_list_all_load_profiles():
    load_profiles = current_app.config.get('LOAD_PROFILES', [])
    return jsonify({"success": True, "data": load_profiles, "message": "All saved load profiles listed."}), 200
