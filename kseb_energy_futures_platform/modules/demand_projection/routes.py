"""
API routes for Demand Projection module features.

This blueprint handles API endpoints for uploading demand data,
running forecasts, and retrieving forecast scenario data (aggregated, sector-wise).
It uses `current_app.config` for storing simulated job statuses and scenario metadata.
"""
from flask import Blueprint, request, jsonify, current_app
import time
import random
import datetime # For timestamping saved scenarios
import os # For file path operations
from werkzeug.utils import secure_filename # For securing uploaded filenames

# Import the parser function
from .file_parser import load_input_demand_data
import pandas as pd # For creating empty DataFrame if needed

# Blueprint for demand projection API, prefixed with /api/demand_projection
demand_projection_bp = Blueprint('demand_projection_api', __name__, url_prefix='/api/demand_projection')

@demand_projection_bp.route('/upload_demand_file', methods=['POST'])
def api_upload_demand_file():
    """
    API endpoint to upload, parse, and process a demand data Excel file.
    The uploaded file is temporarily saved, parsed using `load_input_demand_data`,
    and the extracted data is stored in `current_app.config['PROCESSED_DEMAND_DATA']`.

    Expects a 'demand_file' in `request.files`.

    Returns:
        JSON: Success status, message, and summary of processed data (filename, settings, sectors).
              Returns 400 if no file is provided or file is invalid.
              Returns 500 if parsing fails.
    """
    if 'demand_file' not in request.files:
        return jsonify({"success": False, "message": "No file part in the request."}), 400

    file = request.files['demand_file']
    if file.filename == '':
        return jsonify({"success": False, "message": "No file selected for uploading."}), 400

    if file: # Basic check for allowed extensions could be added here e.g. if not filename.lower().endswith(('.xlsx', '.xls')):
        filename = secure_filename(file.filename)
        upload_folder = current_app.config.get('UPLOAD_FOLDER')
        if not upload_folder: # UPLOAD_FOLDER should be configured in main app.py
            current_app.logger.error("UPLOAD_FOLDER not configured in Flask app.")
            return jsonify({"success": False, "message": "File upload path not configured on server."}), 500

        # Ensure a unique temporary filename in case of concurrent uploads, though less critical for single user simulation
        # For this prototype, simple filename is okay.
        temp_file_path = os.path.join(upload_folder, filename)

        try:
            file.save(temp_file_path)
            current_app.logger.info(f"File '{filename}' saved temporarily to '{temp_file_path}'.")

            # Call the parser function
            parsed_data = load_input_demand_data(temp_file_path)

            if parsed_data is None:
                current_app.logger.error(f"Parsing failed for file '{filename}'.")
                return jsonify({"success": False, "message": f"Failed to parse the uploaded file '{filename}'. Check file format and content."}), 500

            # Store processed data in app.config (for this single-file simulation).
            # This makes the parsed data available to other endpoints/logic within the current app context.
            current_app.config['PROCESSED_DEMAND_DATA'] = parsed_data
            current_app.logger.info(f"Successfully parsed and stored data from '{filename}' into app.config['PROCESSED_DEMAND_DATA'].")

            # Delete the temporary file after successful processing.
            try:
                os.remove(temp_file_path)
                current_app.logger.info(f"Temporary file '{temp_file_path}' deleted.")
            except Exception as e_remove:
                current_app.logger.warning(f"Could not remove temporary file '{temp_file_path}': {e_remove}")

            return jsonify({
                "success": True,
                "message": f"File '{filename}' processed successfully.",
                "data": { # Return a summary of what was processed
                    "filename": filename,
                    "settings": parsed_data.get('settings'),
                    "sectors_found": parsed_data.get('sectors_list'),
                    "num_sector_data_entries": len(parsed_data.get('sector_data', {})),
                    "aggregated_data_summary": {
                        "num_years": len(parsed_data.get('aggregated_electricity', pd.DataFrame())),
                        "columns": parsed_data.get('aggregated_electricity', pd.DataFrame()).columns.tolist()
                    }
                }
            }), 200

        except Exception as e:
            current_app.logger.error(f"Error during file processing for '{filename}': {e}")
            # Clean up temp file if it exists and an error occurred
            if os.path.exists(temp_file_path):
                try:
                    os.remove(temp_file_path)
                except Exception as e_clean:
                    current_app.logger.error(f"Error cleaning up temp file '{temp_file_path}': {e_clean}")
            return jsonify({"success": False, "message": f"An error occurred processing the file: {str(e)}"}), 500
    else: # Should not happen if checks above are done
        return jsonify({"success": False, "message": "Invalid file."}), 400


@demand_projection_bp.route('/chart_data/historical', methods=['GET'])
def api_get_historical_chart_data():
    """
    API endpoint to fetch historical demand data for charting.
    This data is sourced from the `PROCESSED_DEMAND_DATA` in `app.config`,
    which is populated by the `/upload_demand_file` endpoint.

    Returns:
        JSON: Success status, message, and data formatted for the historical demand chart.
              Returns error if no data is processed or data is in incorrect format.
    """
    # Retrieve the globally stored processed data from the last uploaded file.
    processed_data = current_app.config.get('PROCESSED_DEMAND_DATA')

    if not processed_data or 'aggregated_electricity' not in processed_data:
        current_app.logger.warning("Historical chart data requested, but no PROCESSED_DEMAND_DATA found or it's incomplete.")
        return jsonify({
            "success": False,
            "message": "No processed demand data available. Please upload and process a demand file first.",
            "data": {"years": [], "sectors": {}, "title": "Historical Energy Demand (MU) - No Data Available"}
        }), 404

    aggregated_df = processed_data['aggregated_electricity']

    if not isinstance(aggregated_df, pd.DataFrame) or aggregated_df.empty or 'Year' not in aggregated_df.columns:
        return jsonify({
            "success": False,
            "message": "Processed demand data is invalid or empty.",
            "data": {"years": [], "sectors": {}, "title": "Historical Energy Demand (MU) - Invalid Data"}
        }), 500

    years = aggregated_df['Year'].tolist()
    sectors_chart_data = {}

    # Extract sector electricity columns and total
    for col in aggregated_df.columns:
        if col.endswith('_Electricity'): # Catches 'SectorName_Electricity' and 'Total_Electricity'
            # Clean up column name for display (remove _Electricity suffix, replace _ with space)
            display_name = col.replace('_Electricity', '').replace('_', ' ')
            sectors_chart_data[display_name] = aggregated_df[col].tolist()

    return jsonify({
        "success": True,
        "message": "Historical demand data for chart fetched successfully from processed file.",
        "data": {
            "years": years,
            "sectors": sectors_chart_data,
            "chart_type": "line", # Hint for frontend
            "title": "Historical Energy Demand (MU) - From Uploaded File"
        }
    }), 200

# ... (other demand projection API endpoints: /run_forecast, /scenario_data/*, /save_scenario)
# These should remain largely the same as they operate on simulated job outputs or scenario lists,
# not directly on the initially uploaded file's raw parsed data.
# However, a "run_forecast" might now implicitly use current_app.config['PROCESSED_DEMAND_DATA']
# as its input if the design implies that, or it might expect a reference to it.
# For now, keeping them as they were, operating on scenario IDs and job system.

@demand_projection_bp.route('/run_forecast', methods=['POST'])
def api_run_forecast():
    config = request.json
    if not config or not config.get('scenarioName'):
        return jsonify({"success": False, "message": "Scenario name ('scenarioName') is required to run forecast."}), 400

    scenario_name = config.get('scenarioName')
    # Check if processed data is available to base the forecast on.
    # This implies that forecasts are run on the currently loaded dataset.
    processed_demand_data = current_app.config.get('PROCESSED_DEMAND_DATA')
    if not processed_demand_data:
        current_app.logger.warning(f"Forecast run for '{scenario_name}' attempted without processed demand data.")
        return jsonify({"success": False, "message": "Cannot run forecast: No demand data has been uploaded and processed yet. Please upload data first."}), 400

    print(f"Received request to run demand forecast for scenario: {scenario_name} using current processed data (simulated).")
    # In a real application, specific elements from processed_demand_data would be passed to the forecasting engine.
    job_id = f"fcst_{str(uuid.uuid4())[:8]}"

    simulated_jobs = current_app.config.get('SIMULATED_JOBS', {})
    simulated_jobs[job_id] = {
        "type": "forecast",
        "status": "queued",
        "progress": 0.0,
        "start_time": time.time(),
        "config": config,
        "user": request.headers.get("X-User-ID", "sim_user_demand_fc"),
        "input_data_source": "current_processed_file" # Indicate source
    }
    current_app.config['SIMULATED_JOBS'] = simulated_jobs

    return jsonify({
        "success": True,
        "message": f"Demand forecast run for scenario '{scenario_name}' initiated successfully (simulated).",
        "job_id": job_id
    }), 202

@demand_projection_bp.route('/scenario_data/<scenario_id>/aggregated', methods=['GET'])
def api_get_aggregated_forecast_data(scenario_id):
    # ... (implementation remains largely the same, returns simulated forecast data) ...
    print(f"Fetching aggregated forecast data for scenario ID: {scenario_id}")
    years = list(range(datetime.datetime.now().year, datetime.datetime.now().year + 17))
    base_demand_start_year = random.uniform(350, 500)
    annual_growth_rate = random.uniform(0.015, 0.035)
    total_demand_values = [base_demand_start_year * ((1 + annual_growth_rate) ** i) for i in range(len(years))]
    confidence_lower_values = [d * random.uniform(0.92, 0.97) for d in total_demand_values]
    confidence_upper_values = [d * random.uniform(1.03, 1.08) for d in total_demand_values]
    return jsonify({"success": True, "message": "Aggregated forecast data fetched.", "data": {"scenario_id": scenario_id, "years": years, "total_demand": [round(d,2) for d in total_demand_values], "confidence_lower": [round(d,2) for d in confidence_lower_values], "confidence_upper": [round(d,2) for d in confidence_upper_values], "title": f"Aggregated Forecast {scenario_id}"}}), 200

@demand_projection_bp.route('/scenario_data/<scenario_id>/sector_breakdown', methods=['GET'])
def api_get_sector_breakdown_data(scenario_id):
    # ... (implementation remains largely the same, returns simulated forecast data) ...
    target_year = request.args.get('year', 2030, type=int)
    print(f"Fetching sector breakdown for scenario ID: {scenario_id}, Year: {target_year}")
    sectors_data = { "Domestic": {"value": random.uniform(180,280)}, "Commercial": {"value": random.uniform(120,220)}}
    return jsonify({"success": True, "message": "Sector breakdown data fetched.", "data": {"scenario_id": scenario_id, "year": target_year, "sectors": sectors_data, "title": f"Sector Breakdown {scenario_id} ({target_year})" }}), 200

@demand_projection_bp.route('/save_scenario', methods=['POST'])
def api_save_demand_scenario():
    # ... (implementation remains largely the same, saves scenario metadata) ...
    data = request.json; scenario_id = data.get('scenario_id'); scenario_name = data.get('name')
    if not scenario_id or not scenario_name: return jsonify({"success": False, "message": "ID and Name required."}), 400
    demand_scenarios_list = current_app.config.get('DEMAND_SCENARIOS', [])
    if any(s['id'] == scenario_id for s in demand_scenarios_list): return jsonify({"success": False, "message": f"Scenario {scenario_id} already saved."}), 409
    new_entry = {"id": scenario_id, "name": scenario_name, "description": data.get('description', ''), "saved_at": datetime.datetime.now().isoformat()}
    demand_scenarios_list.append(new_entry)
    current_app.config['DEMAND_SCENARIOS'] = demand_scenarios_list
    return jsonify({"success": True, "message": f"Scenario '{scenario_name}' saved.", "data": new_entry}), 201
