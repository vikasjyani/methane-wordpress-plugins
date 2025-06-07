"""
API routes for Load Profile Generation module features.

This blueprint handles API endpoints related to fetching available demand scenarios
for load profile generation, previewing load profiles, initiating generation jobs,
saving generated profiles, and retrieving data for load profile analysis (heatmap,
daily patterns, LDC). It uses `current_app.config` for storing simulated job
statuses and metadata of generated load profiles and available demand scenarios.
"""
from flask import Blueprint, request, jsonify, current_app
import time
import random
import datetime
import uuid # For unique IDs

# Blueprint for load profile API, prefixed with /api/load_profile
load_profile_bp = Blueprint('load_profile_api', __name__, url_prefix='/api/load_profile')

@load_profile_bp.route('/available_demand_scenarios', methods=['GET'])
def api_get_available_demand_scenarios_for_lp():
    """
    API endpoint to retrieve a list of available demand scenarios.
    These scenarios' results (e.g., total annual energy) can serve as input
    for generating corresponding load profiles.

    Returns:
        JSON: Success status, message, and a list of available demand scenarios (id, name).
    """
    demand_scenarios = current_app.config.get('DEMAND_SCENARIOS', [])
    # Format for dropdown display or selection by the client
    formatted_scenarios = [{"id": s["id"], "name": s["name"]} for s in demand_scenarios]
    return jsonify({
        "success": True,
        "data": formatted_scenarios,
        "message": "Available demand scenarios fetched successfully."
    }), 200

@load_profile_bp.route('/preview', methods=['POST'])
def api_preview_load_profile():
    """
    API endpoint to generate a preview of a load profile based on input configuration.
    Expects JSON data with load profile configuration parameters.
    Simulates a typical daily load curve.

    Request JSON Body:
        baseLoadFactor (float, optional): Base load factor for simulation.
        peakLoadFactor (float, optional): Peak load factor for simulation.
        # ... other configuration parameters from the UI ...

    Returns:
        JSON: Success status, message, and simulated preview data (time and load values for a day).
    """
    config = request.json
    print(f"Received request to preview load profile with config: {config}")

    # Simulate preview data generation using some config parameters
    base_load_factor = float(config.get('baseLoadFactor', 0.35))
    peak_load_factor = float(config.get('peakLoadFactor', 0.9))

    # Assume an average load for the purpose of generating a preview shape
    simulated_average_load_mw = random.uniform(2000, 4000)
    min_load_mw = simulated_average_load_mw * base_load_factor
    peak_load_mw = simulated_average_load_mw * peak_load_factor

    preview_load_values = []
    for h in range(24): # Simulate 24 hourly points for a day
        # Create a simple dual-peak daily profile shape
        daily_shape_factor = (
            0.6 * (1 - abs(h - 9) / 9)**3 +  # Morning-ish peak (flatter)
            1.0 * (1 - abs(h - 19) / 7)**3    # Evening peak (sharper)
        ) / 1.6 # Normalize factor sum

        # Combine base load with shaped peak component
        load_value = min_load_mw + (peak_load_mw - min_load_mw) * daily_shape_factor
        load_value *= random.uniform(0.95, 1.05) # Add some noise
        preview_load_values.append(int(load_value))

    preview_data = {
        "time_points": [f"{h:02d}:00" for h in range(24)], # Hourly labels
        "load_values_mw": preview_load_values
    }

    return jsonify({
        "success": True,
        "data": preview_data,
        "message": "Load profile preview data generated successfully (simulated)."
    }), 200

@load_profile_bp.route('/generate', methods=['POST'])
def api_generate_load_profiles():
    """
    API endpoint to initiate a new load profile generation job.
    Expects JSON data with generation configuration.
    A new job is created and added to `SIMULATED_JOBS`.

    Request JSON Body:
        demandScenario (str): ID of the source demand scenario (required).
        profileName (str, optional): Name for the generated load profile.
        startYear (int): Start year for the profile.
        endYear (int): End year for the profile.
        # ... other configuration parameters ...

    Returns:
        JSON: Success status, message, `job_id`, and `profile_name`.
              Returns 400 if 'demandScenario' is missing.
    """
    config = request.json
    if not config or not config.get('demandScenario'):
        return jsonify({"success": False, "message": "Demand scenario ID ('demandScenario') is required."}), 400

    demand_scenario_id = config.get('demandScenario')
    profile_name_base = f"LP_{demand_scenario_id}_{config.get('startYear', 'YYYY')}-{config.get('endYear', 'YYYY')}"
    profile_name = config.get('profileName', profile_name_base) # Use provided name or generate one

    print(f"Received request to generate load profile: {profile_name}")
    job_id = f"lp_gen_{str(uuid.uuid4())[:8]}" # Unique job ID

    simulated_jobs = current_app.config.get('SIMULATED_JOBS', {})
    simulated_jobs[job_id] = {
        "type": "load_profile_generation",
        "status": "queued",
        "progress": 0.0,
        "start_time": time.time(),
        "config": config, # Store submitted config
        "profile_name_intended": profile_name, # Store intended name
        "user": request.headers.get("X-User-ID", "sim_user_lp")
    }
    current_app.config['SIMULATED_JOBS'] = simulated_jobs

    return jsonify({
        "success": True,
        "message": f"Load profile generation for '{profile_name}' initiated successfully (simulated).",
        "job_id": job_id,
        "profile_name": profile_name # Return the name that will be used
    }), 201 # HTTP 201 Created (as a job resource is created)

@load_profile_bp.route('/save_generated', methods=['POST']) # Renamed for clarity
def api_save_generated_load_profile():
    """
    API endpoint to "save" or "finalize" a generated load profile.
    This is typically called after a generation job completes.
    Adds metadata of the generated profile to `LOAD_PROFILES`.

    Request JSON Body:
        job_id (str): The ID of the job that generated the profile (required).
        profile_name (str): The name of the profile (required).
        # config (dict, optional): The original configuration might be passed back or fetched from job_id

    Returns:
        JSON: Success status and message.
              Returns 400 if required fields are missing.
              Returns 404 if the generating job_id is not found or not completed.
              Returns 409 if a profile with this ID already saved.
    """
    data = request.json
    job_id = data.get('job_id') # ID of the job that generated this profile
    profile_name_from_job = data.get('profile_name') # Name confirmed at job start

    if not job_id or not profile_name_from_job:
        return jsonify({"success": False, "message": "Job ID and Profile Name are required for saving."}), 400

    # Check if the job exists and is completed (simplified check)
    job_info = current_app.config.get('SIMULATED_JOBS', {}).get(job_id)
    if not job_info or job_info.get('status') != "completed":
        return jsonify({"success": False, "message": f"Generating job '{job_id}' not found or not completed."}), 404

    load_profiles_list = current_app.config.get('LOAD_PROFILES', [])
    if any(lp['id'] == job_id for lp in load_profiles_list): # Use job_id as the profile's unique ID
        return jsonify({"success": False, "message": f"Load Profile from job '{job_id}' already saved."}), 409

    job_config = job_info.get('config', {}) # Get original config from job
    new_profile_entry = {
        "id": job_id, # Use the job_id as the unique ID for the load profile itself
        "name": profile_name_from_job,
        "demand_scenario_id": job_config.get('demandScenario'),
        "generated_at": datetime.datetime.now().isoformat(),
        "frequency": job_config.get('frequency', 'Hourly'),
        "unit": job_config.get('unit', 'MW'),
        "source_job_id": job_id,
        "simulated_data_path": f"/simulated_platform_storage/load_profiles/{job_id}.csv"
    }
    load_profiles_list.append(new_profile_entry)
    current_app.config['LOAD_PROFILES'] = load_profiles_list

    message = f"Load Profile '{profile_name_from_job}' (ID: {job_id}) saved successfully (simulated)."
    return jsonify({"success": True, "message": message, "data": new_profile_entry}), 201


@load_profile_bp.route('/data/<profile_id>/heatmap', methods=['GET'])
def api_get_load_profile_heatmap_data(profile_id):
    """
    API endpoint to fetch data for a load profile heatmap.
    Args:
        profile_id (str): The ID of the load profile.

    Returns:
        JSON: Success status, message, and heatmap data (z, x, y values).
              Returns 404 if profile_id is not found.
    """
    # Verify profile_id exists in current_app.config['LOAD_PROFILES']
    if not any(lp['id'] == profile_id for lp in current_app.config.get('LOAD_PROFILES', [])):
        return jsonify({"success": False, "message": f"Load profile with ID '{profile_id}' not found."}), 404

    print(f"Fetching heatmap data for load profile ID: {profile_id}")
    # Simulate detailed data for a Plotly heatmap (e.g., load per hour for each day of a sample period)
    days_in_sample = 90 # Simulate for 3 months
    hours_in_day = 24
    heatmap_z_values = [[random.randint(1500, 4500) for _ in range(hours_in_day)] for _ in range(days_in_sample)]
    heatmap_x_labels = [f"{h:02d}:00" for h in range(hours_in_day)]
    heatmap_y_labels = [(datetime.date(2025,1,1) + datetime.timedelta(days=d)).strftime('%Y-%m-%d') for d in range(days_in_sample)]

    return jsonify({
        "success": True,
        "data": {"z": heatmap_z_values, "x": heatmap_x_labels, "y": heatmap_y_labels, "type": "heatmap", "colorscale": "YlGnBu"},
        "profile_id": profile_id,
        "message": "Load profile heatmap data fetched (simulated)."
    }), 200

@load_profile_bp.route('/data/<profile_id>/daily_pattern', methods=['GET'])
def api_get_load_profile_daily_pattern_data(profile_id):
    """
    API endpoint to fetch average daily load pattern data.
    Args:
        profile_id (str): The ID of the load profile.

    Returns:
        JSON: Success status, message, and daily pattern data (time, weekday/weekend averages).
    """
    if not any(lp['id'] == profile_id for lp in current_app.config.get('LOAD_PROFILES', [])):
        return jsonify({"success": False, "message": f"Load profile with ID '{profile_id}' not found."}), 404

    print(f"Fetching daily pattern data for load profile ID: {profile_id}")
    time_points = [f"{h:02d}:00" for h in range(24)]
    # Simulate slightly different weekday and weekend profiles
    weekday_avg_load = [random.randint(1800, 3800) + int(400 * (1 - abs(h-13)/13)**2 + 200 * (1-abs(h-19)/10)**2 ) for h in range(24)]
    weekend_avg_load = [random.randint(1600, 3200) + int(300 * (1 - abs(h-14)/14)**2 + 150 * (1-abs(h-20)/8)**2 ) for h in range(24)]

    return jsonify({
        "success": True,
        "data": {"time_points": time_points, "weekday_average_mw": weekday_avg_load, "weekend_average_mw": weekend_avg_load},
        "profile_id": profile_id,
        "message": "Daily load pattern data fetched (simulated)."
    }), 200

@load_profile_bp.route('/data/<profile_id>/ldc', methods=['GET'])
def api_get_load_profile_ldc_data(profile_id):
    """
    API endpoint to fetch data for a Load Duration Curve (LDC).
    Args:
        profile_id (str): The ID of the load profile.

    Returns:
        JSON: Success status, message, and LDC data (sorted load values and duration percentages).
    """
    if not any(lp['id'] == profile_id for lp in current_app.config.get('LOAD_PROFILES', [])):
        return jsonify({"success": False, "message": f"Load profile with ID '{profile_id}' not found."}), 404

    print(f"Fetching LDC data for load profile ID: {profile_id}")
    num_data_points = 8760 # Standard for hourly data over a year
    # Simulate a sorted list of load values (highest to lowest)
    ldc_load_values_mw = sorted([random.gauss(2800, 800) for _ in range(num_data_points)], reverse=True)
    # Ensure no negative loads from gauss distribution, cap reasonably
    ldc_load_values_mw = [max(500, min(val, 6000)) for val in ldc_load_values_mw]

    duration_percentage_points = [(i / num_data_points) * 100 for i in range(num_data_points)]

    return jsonify({
        "success": True,
        "data": {"load_values_mw": ldc_load_values_mw, "duration_percent": duration_percentage_points},
        "profile_id": profile_id,
        "message": "Load Duration Curve data fetched (simulated)."
    }), 200

@load_profile_bp.route('/list_all', methods=['GET']) # Renamed for clarity
def api_list_all_load_profiles():
    """
    API endpoint to retrieve a list of all generated and saved load profiles.

    Returns:
        JSON: Success status, message, and a list of load profile metadata objects.
    """
    load_profiles = current_app.config.get('LOAD_PROFILES', [])
    return jsonify({"success": True, "data": load_profiles, "message": "All saved load profiles listed."}), 200
