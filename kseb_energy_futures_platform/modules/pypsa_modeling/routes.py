"""
API routes for PyPSA Co-optimisation Modeling module features.

This blueprint handles API endpoints for validating PyPSA model configurations,
initiating simulation runs, and fetching related data like available load profiles.
Job statuses for PyPSA runs are handled by a generic job status endpoint in the main app.
It uses `current_app.config` for storing simulated job information and accessing
other configurations like available load profiles.
"""
from flask import Blueprint, request, jsonify, current_app
import time
import random
import datetime
import uuid # For unique job IDs

# Blueprint for PyPSA modeling API, prefixed with /api/pypsa_model
pypsa_modeling_bp = Blueprint('pypsa_modeling_api', __name__, url_prefix='/api/pypsa_model')

@pypsa_modeling_bp.route('/validate_configuration', methods=['POST']) # More specific route name
def api_validate_pypsa_model_configuration():
    """
    API endpoint to simulate the validation of a PyPSA model configuration.
    Expects JSON data with PyPSA scenario configuration details.

    Request JSON Body:
        scenarioName (str, optional): Name of the scenario being configured.
        overrideYears (str, optional): Custom investment years (CSV).
        generatorClustering (str, optional): Generator clustering strategy.
        transmissionExpansion (str, optional): Transmission expansion strategy.
        solver (str, optional): Selected solver (e.g., 'gurobi', 'cbc').
        # ... other PyPSA specific configuration parameters ...

    Returns:
        JSON: Success status, message, and detailed validation status (simulated).
              Returns 400 if no configuration data is provided.
    """
    config_data = request.json
    if not config_data:
        return jsonify({"success": False, "message": "No configuration data provided for PyPSA model validation."}), 400

    scenario_name = config_data.get('scenarioName', 'Unnamed PyPSA Scenario')
    print(f"Received request to validate PyPSA model configuration for scenario: {scenario_name}")

    # Simulate a delay for validation process
    time.sleep(random.uniform(0.5, 2.0))

    # Simulate various validation checks
    validation_status_details = {
        "network_data_consistency": random.choice([True, True, False]), # e.g., bus names match in all files
        "cost_data_completeness": random.choice([True, True, True, False]), # e.g., all technologies have costs
        "load_profile_link_valid": random.choice([True, False]), # e.g., selected load profile exists and has data
        "re_potentials_format_ok": True,
        "solver_options_compatible": random.choice([True, True, False]) if config_data.get('solver') == 'cbc' else True, # CBC might have fewer options
        "constraint_definitions_valid": True
    }
    all_checks_passed = all(validation_status_details.values())

    message = f"PyPSA model configuration validation for '{scenario_name}' "
    message += "completed successfully." if all_checks_passed else "completed with issues (simulated)."

    return jsonify({
        "success": True, # API call itself succeeded
        "message": message,
        "data": {
            "scenario_name": scenario_name,
            "validation_status_details": validation_status_details,
            "all_checks_passed": all_checks_passed,
            "validated_at": datetime.datetime.now().isoformat()
        }
    }), 200

@pypsa_modeling_bp.route('/run_simulation', methods=['POST']) # More specific route name
def api_run_pypsa_simulation():
    """
    API endpoint to initiate a new PyPSA co-optimisation simulation run.
    Expects JSON data with the scenario configuration.
    A new job is created and added to `SIMULATED_JOBS`.

    Request JSON Body:
        scenarioName (str): Name for this PyPSA simulation scenario (required).
        scenarioDescription (str, optional): Description for the scenario.
        loadProfileId (str, optional): ID of the load profile to be used.
        # ... other PyPSA configuration parameters from the UI ...

    Returns:
        JSON: Success status, message, `job_id`, and `scenario_name`.
              Returns 400 if 'scenarioName' is missing.
    """
    config_data = request.json
    scenario_name = config_data.get('scenarioName')

    if not scenario_name:
        return jsonify({"success": False, "message": "Scenario name ('scenarioName') is required to run PyPSA simulation."}), 400

    load_profile_id = config_data.get('loadProfileId')
    # In a real app, validate that this load_profile_id exists and is suitable.
    if not load_profile_id:
        print(f"Warning for PyPSA run '{scenario_name}': No explicit 'loadProfileId' provided. Simulation might use a default or fail if required.")
        # For simulation, we can assign a placeholder if needed by other parts of the mock logic.
        load_profile_id = "placeholder_lp_for_pypsa_run"


    print(f"Received request to run PyPSA simulation for scenario: {scenario_name}")
    job_id = f"pypsa_{str(uuid.uuid4())[:8]}" # Unique job ID with prefix

    simulated_jobs = current_app.config.get('SIMULATED_JOBS', {})
    simulated_jobs[job_id] = {
        "type": "pypsa", # Job type for generic status polling
        "status": "queued",
        "progress": 0.0,
        "start_time": time.time(),
        "config": config_data, # Store the full submitted configuration
        "scenario_name": scenario_name, # Store for easy reference
        "associated_load_profile_id": load_profile_id, # Link to the load profile used
        "user": request.headers.get("X-User-ID", "sim_user_pypsa_modeler")
    }
    current_app.config['SIMULATED_JOBS'] = simulated_jobs

    return jsonify({
        "success": True,
        "message": f"PyPSA simulation run for scenario '{scenario_name}' initiated successfully (simulated).",
        "job_id": job_id,
        "scenario_name": scenario_name # Return the name used
    }), 202 # HTTP 202 Accepted

@pypsa_modeling_bp.route('/available_load_profiles', methods=['GET'])
def api_get_available_load_profiles_for_pypsa_config():
    """
    API endpoint to retrieve a list of available (generated) load profiles.
    This is used to populate selection dropdowns in the PyPSA configuration UI.

    Returns:
        JSON: Success status, message, and a list of available load profiles (id, name).
    """
    load_profiles = current_app.config.get('LOAD_PROFILES', [])
    # Format for dropdown display: typically needs 'id' and 'name'
    formatted_profiles = [{"id": lp["id"], "name": lp["name"]} for lp in load_profiles]
    return jsonify({
        "success": True,
        "data": formatted_profiles,
        "message": "Available load profiles for PyPSA fetched successfully."
    }), 200

# The generic job status endpoint `/api/job_status/<job_id>` is defined in `app.py`.
# It will handle status requests for jobs initiated by this blueprint as well,
# by checking the 'type' field of the job in `SIMULATED_JOBS`.
