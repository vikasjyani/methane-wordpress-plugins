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

# Blueprint for demand projection API, prefixed with /api/demand_projection
demand_projection_bp = Blueprint('demand_projection_api', __name__, url_prefix='/api/demand_projection')

@demand_projection_bp.route('/upload_demand_file', methods=['POST'])
def api_upload_demand_file():
    """
    API endpoint to simulate the upload and initial processing of a demand data file.
    In a real application, this would handle file storage and parsing.
    Here, it returns simulated file details.

    Returns:
        JSON: Success status, message, and simulated details of the uploaded file.
    """
    # Actual file handling with request.files would be here.
    print("Simulating demand data file upload and basic validation...")

    simulated_file_name = f"historical_demand_{str(uuid.uuid4())[:4]}.xlsx" # Example name
    simulated_file_details = {
        "name": simulated_file_name,
        "size_kb": random.randint(200, 5000), # Simulated file size in KB
        "rows_detected": random.randint(100, 8760 * 5), # e.g. 5 years of hourly data
        "columns_detected": random.randint(3, 10),
        "date_range_found": f"2018-01-01 to 2023-12-31 (simulated)",
        "data_quality_issues": random.randint(0,5) # Number of simulated quality issues
    }

    # Potentially store info about the last uploaded file in app.config for other endpoints to reference
    # current_app.config['CURRENT_DEMAND_DATA_INFO'] = simulated_file_details

    return jsonify({
        "success": True,
        "message": f"File '{simulated_file_name}' processed and validated (simulated).",
        "file_status": f"{simulated_file_name} loaded.", # For UI display
        "data": simulated_file_details # Detailed info about the "processed" file
    }), 200

@demand_projection_bp.route('/chart_data/historical', methods=['GET'])
def api_get_historical_chart_data():
    """
    API endpoint to fetch simulated historical demand data for charting.
    This data would typically come from a processed uploaded file.

    Returns:
        JSON: Success status, message, and data for historical demand chart
              (years, sector-wise demand, title).
    """
    print("Fetching simulated historical demand data for chart...")
    years = [2019, 2020, 2021, 2022, 2023]
    sectors_data = { # Demand in MU (Million Units)
        "Domestic": [random.randint(95,125) for _ in years],
        "Commercial": [random.randint(65,95) for _ in years],
        "Industrial_HT": [random.randint(170,230) for _ in years],
        "Agricultural": [random.randint(150,190) for _ in years],
    }
    # Calculate total demand based on the sum of individual sectors for consistency
    total_demand = [sum(sectors_data[sector][i] for sector in sectors_data) for i in range(len(years))]
    sectors_data["Total"] = total_demand

    return jsonify({
        "success": True,
        "message": "Historical demand data for chart fetched successfully (simulated).",
        "data": {
            "years": years,
            "sectors": sectors_data, # Dictionary of lists {sector_name: [values_for_years]}
            "chart_type": "line",
            "title": "Historical Energy Demand (MU)"
        }
    }), 200

@demand_projection_bp.route('/run_forecast', methods=['POST'])
def api_run_forecast():
    """
    API endpoint to initiate a new demand forecast run.
    Expects JSON data with forecast configuration details.
    A new job is created and added to the `SIMULATED_JOBS` store.

    Request JSON Body:
        scenarioName (str): Name for the forecast scenario (required).
        targetYear (int): The final year for the forecast horizon.
        endYear (int): Same as targetYear, can be used interchangeably.
        excludeCovid (bool, optional): Flag to exclude COVID-19 anomaly years.
        modelConfigs (list, optional): List of sector-specific model configurations.
        # ... other potential configuration parameters ...

    Returns:
        JSON: Success status, message, and the `job_id` for the initiated forecast run.
              Returns 400 if 'scenarioName' is missing.
    """
    config = request.json
    if not config or not config.get('scenarioName'):
        return jsonify({"success": False, "message": "Scenario name ('scenarioName') is required to run forecast."}), 400

    scenario_name = config.get('scenarioName')
    print(f"Received request to run demand forecast for scenario: {scenario_name}")
    job_id = f"fcst_{str(uuid.uuid4())[:8]}" # Unique job ID

    simulated_jobs = current_app.config.get('SIMULATED_JOBS', {})
    simulated_jobs[job_id] = {
        "type": "forecast", # Job type for generic status polling
        "status": "queued",
        "progress": 0.0,
        "start_time": time.time(), # Record submission time
        "config": config, # Store the received configuration
        "user": request.headers.get("X-User-ID", "sim_user_demand_fc") # Example: get user from header
    }
    current_app.config['SIMULATED_JOBS'] = simulated_jobs

    return jsonify({
        "success": True,
        "message": f"Demand forecast run for scenario '{scenario_name}' initiated successfully (simulated).",
        "job_id": job_id
    }), 202 # HTTP 202 Accepted: request is accepted for processing

@demand_projection_bp.route('/scenario_data/<scenario_id>/aggregated', methods=['GET'])
def api_get_aggregated_forecast_data(scenario_id):
    """
    API endpoint to fetch aggregated results for a specific demand forecast scenario.
    Args:
        scenario_id (str): The ID of the forecast scenario.

    Returns:
        JSON: Success status, message, and aggregated forecast data (years, total demand, confidence intervals).
    """
    print(f"Fetching aggregated forecast data for scenario ID: {scenario_id}")

    # Simulate data generation; in a real app, this would fetch from stored results linked to scenario_id
    years = list(range(datetime.datetime.now().year, datetime.datetime.now().year + 17)) # e.g., 2024 to 2040
    base_demand_start_year = random.uniform(350, 500) # Base demand for the first year
    annual_growth_rate = random.uniform(0.015, 0.035) # Simulated average annual growth

    total_demand_values = [base_demand_start_year * ((1 + annual_growth_rate) ** i) for i in range(len(years))]
    # Simulate confidence bounds as a percentage of the mean
    confidence_lower_values = [d * random.uniform(0.92, 0.97) for d in total_demand_values]
    confidence_upper_values = [d * random.uniform(1.03, 1.08) for d in total_demand_values]

    return jsonify({
        "success": True,
        "message": f"Aggregated forecast data for scenario '{scenario_id}' fetched successfully (simulated).",
        "data": {
            "scenario_id": scenario_id,
            "years": years,
            "total_demand": [round(d, 2) for d in total_demand_values],
            "confidence_lower": [round(d, 2) for d in confidence_lower_values],
            "confidence_upper": [round(d, 2) for d in confidence_upper_values],
            "chart_type": "line_with_confidence_interval", # Hint for frontend charting
            "title": f"Aggregated Demand Forecast for {scenario_id} (MU)"
        }
    }), 200

@demand_projection_bp.route('/scenario_data/<scenario_id>/sector_breakdown', methods=['GET'])
def api_get_sector_breakdown_data(scenario_id):
    """
    API endpoint to fetch sector-wise breakdown for a specific demand forecast scenario.
    Args:
        scenario_id (str): The ID of the forecast scenario.
        year (int, optional query param): The specific year for which breakdown is requested. Defaults to 2030.

    Returns:
        JSON: Success status, message, and sector-wise forecast data for the specified year.
    """
    target_year = request.args.get('year', 2030, type=int)
    print(f"Fetching sector breakdown for scenario ID: {scenario_id}, Year: {target_year}")

    # Simulate sector data; this would be more complex in reality
    sectors_data = {
        "Domestic": {"value": random.uniform(180,280), "growth_from_base_percent": random.uniform(20, 60)},
        "Commercial": {"value": random.uniform(120,220), "growth_from_base_percent": random.uniform(15, 50)},
        "Industrial_HT": {"value": random.uniform(100,200), "growth_from_base_percent": random.uniform(10, 40)},
        "Agricultural": {"value": random.uniform(160,200), "growth_from_base_percent": random.uniform(5, 25)},
        "Other_LT_Bulk": {"value": random.uniform(25,50), "growth_from_base_percent": random.uniform(5, 30)}
    }

    return jsonify({
        "success": True,
        "message": f"Sector breakdown data for scenario '{scenario_id}' (Year: {target_year}) fetched (simulated).",
        "data": {
            "scenario_id": scenario_id,
            "year": target_year,
            "sectors": {k: {**v, "value": round(v["value"],2), "growth_from_base_percent": round(v["growth_from_base_percent"],1)} for k,v in sectors_data.items()},
            "chart_type": "pie_or_stacked_bar", # Hint for frontend
            "title": f"Sectoral Demand Breakdown for {scenario_id} in {target_year} (MU)"
        }
    }), 200

@demand_projection_bp.route('/save_scenario', methods=['POST']) # Renamed from save_consolidated_data
def api_save_demand_scenario():
    """
    API endpoint to "save" or "finalize" a demand forecast scenario after a run.
    This would typically involve moving temporary results to a permanent store
    and making the scenario available for other modules.
    Expects JSON data with scenario details.

    Request JSON Body:
        scenario_id (str): The ID of the job that generated the scenario (required).
        name (str): The user-defined name for this scenario (required).
        description (str, optional): A description for this scenario.
        base_year (int, optional): The base year of the forecast.
        forecast_horizon_years (list, optional): List of years in the forecast.
        # ... other relevant metadata from the forecast job or user input ...

    Returns:
        JSON: Success status and message.
              Returns 400 if required fields are missing.
              Returns 409 if a scenario with the same ID already exists.
    """
    data = request.json
    scenario_id = data.get('scenario_id') # This should be the job_id that generated the results
    scenario_name = data.get('name')

    if not scenario_id or not scenario_name:
        return jsonify({"success": False, "message": "Scenario ID and Name are required to save."}), 400

    print(f"Received request to save demand scenario: {scenario_name} (from job_id: {scenario_id})")

    demand_scenarios_list = current_app.config.get('DEMAND_SCENARIOS', [])
    if any(s['id'] == scenario_id for s in demand_scenarios_list): # Check if job_id already saved as a scenario
        return jsonify({"success": False, "message": f"Scenario results from job '{scenario_id}' have already been saved."}), 409 # Conflict

    # Assume the job (scenario_id) has completed; fetch its config or use passed data
    job_info = current_app.config.get('SIMULATED_JOBS', {}).get(scenario_id, {})
    job_config = job_info.get('config', {})

    new_scenario_entry = {
        "id": scenario_id, # Use job_id as the scenario_id for traceability
        "name": scenario_name,
        "description": data.get('description', job_config.get('scenarioDescription', 'No description provided.')),
        "base_year": job_config.get('targetYear', data.get('base_year')), # From job or explicit save call
        "forecast_horizon_end_year": job_config.get('endYear', data.get('end_year')),
        "created_from_job_id": scenario_id,
        "saved_at": datetime.datetime.now().isoformat(),
        "source_data_simulated": "uploaded_historical_demand.xlsx (simulated)", # Example
        "model_type_simulated": "ARIMA/Regression (simulated)" # Example
    }
    demand_scenarios_list.append(new_scenario_entry)
    current_app.config['DEMAND_SCENARIOS'] = demand_scenarios_list

    message = f"Demand scenario '{scenario_name}' saved successfully (simulated)."
    return jsonify({"success": True, "message": message, "data": new_scenario_entry}), 201 # 201 Created (new resource)
