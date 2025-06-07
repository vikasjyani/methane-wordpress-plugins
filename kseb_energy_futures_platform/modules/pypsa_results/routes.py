"""
API routes for PyPSA Results Visualization module features.

This blueprint handles API endpoints for listing available PyPSA simulation results (scenarios),
fetching detailed data for a specific scenario (key metrics, investment timelines,
system evolution, dispatch profiles, capacity details), and comparing multiple scenarios.
It relies on `current_app.config` for accessing metadata of completed PyPSA scenarios
and simulating the retrieval of their detailed results.
"""
from flask import Blueprint, request, jsonify, current_app
import random
import datetime
import uuid

# Blueprint for PyPSA results API, prefixed with /api/pypsa_results
pypsa_results_bp = Blueprint('pypsa_results_api', __name__, url_prefix='/api/pypsa_results')

@pypsa_results_bp.route('/available_scenarios', methods=['GET'])
def api_get_pypsa_available_results_scenarios():
    """
    API endpoint to retrieve a list of available (completed) PyPSA simulation scenarios.
    These are scenarios whose results can be viewed and analyzed.

    Returns:
        JSON: Success status, message, and a list of PyPSA scenario metadata objects.
    """
    pypsa_scenarios = current_app.config.get('PYPSA_SCENARIOS', [])
    # Sort by creation date descending if 'created' field exists and is consistent
    try:
        sorted_scenarios = sorted(pypsa_scenarios, key=lambda s: s.get('created', ''), reverse=True)
    except TypeError: # Handles cases where 'created' might be missing or not comparable
        sorted_scenarios = pypsa_scenarios

    return jsonify({
        "success": True,
        "data": sorted_scenarios,
        "message": "Available PyPSA result scenarios fetched successfully."
    }), 200

@pypsa_results_bp.route('/metrics/<scenario_id>', methods=['GET'])
def api_get_pypsa_key_metrics_data(scenario_id): # Renamed for clarity
    """
    API endpoint to fetch key performance metrics for a specific PyPSA scenario.
    Args:
        scenario_id (str): The ID of the PyPSA scenario.

    Returns:
        JSON: Success status, message, scenario ID, and key metrics data (simulated).
              Returns 404 if scenario_id is not found in PYPSA_SCENARIOS.
    """
    all_scenarios = current_app.config.get('PYPSA_SCENARIOS', [])
    scenario_info = next((s for s in all_scenarios if s["id"] == scenario_id), None)

    if not scenario_info:
        return jsonify({"success": False, "message": f"PyPSA scenario with ID '{scenario_id}' not found."}), 404

    print(f"Fetching key metrics for PyPSA scenario: {scenario_id}")

    # Simulate metrics, potentially varying them based on scenario name or description if desired
    cost_factor = 1.0; re_factor = 1.0; emission_factor = 1.0
    if "HighRE" in scenario_info.get("name", ""): cost_factor = 1.08; re_factor = 1.25; emission_factor = 0.7
    elif "CarbonCap" in scenario_info.get("name", ""): cost_factor = 1.03; re_factor = 1.1; emission_factor = 0.5

    metrics_data = {
        "total_system_cost_billion_inr": round(random.uniform(90000, 220000) * cost_factor, 2),
        "co2_emissions_mt_annum": round(random.uniform(1.5, 9.5) * emission_factor, 2),
        "renewable_energy_share_percent": min(100.0, round(random.uniform(35.0, 75.0) * re_factor, 1)),
        "total_curtailment_gwh_annum": round(random.uniform(80, 2200) * re_factor, 0)
    }

    return jsonify({
        "success": True,
        "scenario_id": scenario_id,
        "data": metrics_data,
        "message": f"Key metrics for PyPSA scenario '{scenario_id}' fetched successfully (simulated)."
    }), 200

@pypsa_results_bp.route('/investment_timeline/<scenario_id>', methods=['GET'])
def api_get_pypsa_investment_timeline_data(scenario_id): # Renamed for clarity
    """
    API endpoint to fetch data for the investment timeline chart (e.g., capacity additions).
    Args:
        scenario_id (str): The ID of the PyPSA scenario.

    Returns:
        JSON: Success status, message, and data for the investment timeline chart.
              Returns 404 if scenario_id is not found.
    """
    if not any(s['id'] == scenario_id for s in current_app.config.get('PYPSA_SCENARIOS', [])):
        return jsonify({"success": False, "message": f"PyPSA scenario '{scenario_id}' not found."}), 404

    print(f"Fetching investment timeline data for PyPSA scenario: {scenario_id}")
    investment_years = [2025, 2028, 2030, 2033, 2035, 2038, 2040] # Example investment periods
    investment_data = {
        "years": investment_years,
        "technologies_mw_added": { # Capacity added in MW per period
            "Solar_PV": [random.randint(40,350) for _ in investment_years],
            "Onshore_Wind": [random.randint(20,250) for _ in investment_years],
            "Offshore_Wind": [random.randint(0,150) for _ in investment_years], # May not always be invested
            "Battery_Storage_MW": [random.randint(10,120) for _ in investment_years], # Power capacity
            "HVDC_Transmission_GWkm": [random.randint(0,50) for _ in investment_years] # Proxy for transmission
        },
        "title": f"Investment Timeline for {scenario_id} (Simulated Capacity Additions - MW or GWkm)"
    }
    return jsonify({"success": True, "data": investment_data, "message": "Investment timeline data fetched."}), 200

@pypsa_results_bp.route('/system_evolution/<scenario_id>', methods=['GET'])
def api_get_pypsa_system_evolution_data(scenario_id):
    """
    API endpoint to fetch data for the system evolution chart (e.g., generation mix over time).
    Args:
        scenario_id (str): The ID of the PyPSA scenario.

    Returns:
        JSON: Success status, message, and data for the system evolution chart.
              Returns 404 if scenario_id is not found.
    """
    if not any(s['id'] == scenario_id for s in current_app.config.get('PYPSA_SCENARIOS', [])):
        return jsonify({"success": False, "message": f"PyPSA scenario '{scenario_id}' not found."}), 404

    print(f"Fetching system evolution data for PyPSA scenario: {scenario_id}")
    evolution_years = [2025, 2030, 2035, 2040]
    evolution_data = {
        "years": evolution_years,
        "generation_mix_twh_annum": { # Annual generation in TWh
            "Coal_Thermal": [max(0, random.randint(30,60) * (1-(i*0.2))) for i, _ in enumerate(evolution_years)], # Decreasing
            "Gas_CCGT": [max(0, random.randint(5,20) * (1-(i*0.1))) for i, _ in enumerate(evolution_years)], # Decreasing or phased out
            "Hydro_Reservoir": [random.randint(18,28) for _ in evolution_years], # Relatively constant
            "Solar_PV_Utility": [random.randint(15,70) * (i+1) for i, _ in enumerate(evolution_years)], # Increasing
            "Onshore_Wind": [random.randint(10,60) * (i+1) for i, _ in enumerate(evolution_years)], # Increasing
            "Offshore_Wind": [random.randint(0,40) * (i+1) for i, _ in enumerate(evolution_years)], # Increasing, may start later
            "Battery_Storage_Discharge": [random.randint(1,15) * (i+1) for i, _ in enumerate(evolution_years)],
            "Net_Imports": [random.randint(-5,10) for _ in evolution_years] # Can be negative (exports)
        },
        "title": f"System Generation Evolution for {scenario_id} (TWh/annum)"
    }
    return jsonify({"success": True, "data": evolution_data, "message": "System evolution data fetched."}), 200

@pypsa_results_bp.route('/dispatch_profile/<scenario_id>', methods=['GET']) # Renamed for clarity
def api_get_pypsa_dispatch_profile_data(scenario_id): # Renamed for clarity
    """
    API endpoint to fetch dispatch profile data for a sample period.
    Args:
        scenario_id (str): The ID of the PyPSA scenario.

    Returns:
        JSON: Success status, message, and dispatch data (timestamps, generation per tech, demand, curtailment).
              Returns 404 if scenario_id is not found.
    """
    if not any(s['id'] == scenario_id for s in current_app.config.get('PYPSA_SCENARIOS', [])):
        return jsonify({"success": False, "message": f"PyPSA scenario '{scenario_id}' not found."}), 404

    print(f"Fetching dispatch profile data for PyPSA scenario: {scenario_id}")
    num_hours_sample = 7 * 24 # One week sample
    start_datetime = datetime.datetime(2030, 7, 15, 0, 0) # Sample start date (e.g., a summer week)
    timestamps = [(start_datetime + datetime.timedelta(hours=h)).isoformat() for h in range(num_hours_sample)]

    # Simulate generation for different technologies (MW)
    solar_gen_mw = [max(0, round(random.uniform(0.6,1.0) * 3500 * (1 - abs(h % 24 - 13) / 7)**2.5,1) ) if 5 <= (h % 24) <= 19 else 0 for h in range(num_hours_sample)]
    wind_gen_mw = [round(random.uniform(0.2, 0.9) * 2500 * (random.uniform(0.7,1.3)),1) for h in range(num_hours_sample)] # Variable wind
    hydro_gen_mw = [round(random.uniform(100, 400) + (50 * ((h%24)/24)),1) for h in range(num_hours_sample)] # Some daily shaping
    thermal_gen_mw = [round(random.uniform(800, 2000),1) for h in range(num_hours_sample)] # Baseload-ish

    total_generation_mw = [sum(gens) for gens in zip(solar_gen_mw, wind_gen_mw, hydro_gen_mw, thermal_gen_mw)]
    demand_mw = [max(1500, g * random.uniform(0.85, 0.98)) for g in total_generation_mw] # Demand slightly less than generation for this sim
    solar_curtailment_mw = [max(0, s_g - (d_mw * 0.5) + random.uniform(-100,100)) for s_g, d_mw in zip(solar_gen_mw, demand_mw)] # Simplified curtailment logic

    dispatch_data = {
        "timestamps_iso": timestamps,
        "generation_profile_mw": {
            "Solar_PV": solar_gen_mw, "Wind_Onshore": wind_gen_mw, "Hydro_ROR": hydro_gen_mw, "Thermal_Flexible": thermal_gen_mw,
        },
        "demand_profile_mw": demand_mw,
        "curtailment_profile_mw": {"Solar_PV": solar_curtailment_mw, "Wind_Onshore": [round(random.uniform(0,30),1) for _ in range(num_hours_sample)]},
        "title": f"Dispatch Profile for {scenario_id} (Sample Week - MW)"
    }
    return jsonify({"success": True, "data": dispatch_data, "message": "Dispatch profile data fetched."}), 200

@pypsa_results_bp.route('/capacity_summary/<scenario_id>', methods=['GET']) # Renamed for clarity
def api_get_pypsa_capacity_summary_data(scenario_id): # Renamed for clarity
    """
    API endpoint to fetch installed capacity summary for a specific PyPSA scenario and year.
    Args:
        scenario_id (str): The ID of the PyPSA scenario.
        year (int, optional query param): The target year for capacity summary. Defaults to 2030.

    Returns:
        JSON: Success status, message, and capacity data (MW/MWh per technology).
              Returns 404 if scenario_id is not found.
    """
    if not any(s['id'] == scenario_id for s in current_app.config.get('PYPSA_SCENARIOS', [])):
        return jsonify({"success": False, "message": f"PyPSA scenario '{scenario_id}' not found."}), 404

    target_year = request.args.get('year', 2030, type=int)
    print(f"Fetching capacity summary for PyPSA scenario: {scenario_id}, Year: {target_year}")

    capacity_data = {
        "target_year": target_year,
        "installed_capacity_mw": { # Power capacity in MW
            "Coal_Thermal_Existing": random.randint(800,1800),
            "Gas_CCGT_New": random.randint(0,500),
            "Hydro_Reservoir_Existing": random.randint(600,1200),
            "Solar_PV_Utility_New": random.randint(2500, 7000),
            "Onshore_Wind_New": random.randint(1800, 5000),
            "Offshore_Wind_New": random.randint(0, 2000),
            "Battery_Storage_New_Power": random.randint(400,1200),
        },
        "storage_energy_capacity_mwh": { # Energy capacity for storage in MWh
             "Battery_Storage_New_Energy": random.randint(1600,4800) # e.g. 4-hour duration
        },
        "title": f"Installed Capacity Summary for {scenario_id} in {target_year} (MW/MWh)"
    }
    return jsonify({"success": True, "data": capacity_data, "message": "Capacity summary data fetched."}), 200


@pypsa_results_bp.route('/compare_scenarios_data', methods=['POST']) # Renamed for clarity
def api_pypsa_compare_scenarios_data(): # Renamed for clarity
    """
    API endpoint to fetch data for comparing multiple PyPSA scenarios based on selected metrics.
    Expects JSON data with a list of scenario IDs and a list of metric keys.

    Request JSON Body:
        scenario_ids (list): List of PyPSA scenario IDs to compare.
        metrics (list): List of metric keys (e.g., "total_system_cost", "re_share_percent").

    Returns:
        JSON: Success status, message, and data structured for comparison table and charts.
              Returns 400 if scenario_ids or metrics are missing.
    """
    req_data = request.json
    scenario_ids_to_compare = req_data.get('scenario_ids', [])
    metrics_to_compare = req_data.get('metrics', [])

    if not scenario_ids_to_compare or not metrics_to_compare:
        return jsonify({"success": False, "message": "Scenario IDs and Metrics for comparison are required."}), 400

    print(f"Fetching data for comparing PyPSA scenarios: {scenario_ids_to_compare} for metrics: {metrics_to_compare}")

    # Simulate fetching data for each scenario and structuring it for comparison
    comparison_table_rows = []
    for metric_key in metrics_to_compare:
        metric_display_name = metric_key.replace("_", " ").title() # Format for display
        row_data = {"metric_name": metric_display_name}
        for scen_id in scenario_ids_to_compare:
            # Simulate metric value for this scenario (this would call helper functions in a real app)
            if metric_key == "total_system_cost_billion_inr": row_data[scen_id] = f"{random.uniform(90000, 230000):.0f}"
            elif metric_key == "re_share_percent": row_data[scen_id] = f"{random.uniform(30, 80):.1f}%"
            elif metric_key == "co2_emissions_mt_annum": row_data[scen_id] = f"{random.uniform(1.0, 10.0):.1f}"
            elif metric_key == "avg_electricity_price_inr_kwh": row_data[scen_id] = f"₹{random.uniform(2.8, 6.5):.2f}"
            elif metric_key == "re_curtailment_gwh_annum": row_data[scen_id] = f"{random.uniform(100, 2500):.0f}"
            else: row_data[scen_id] = "N/A (Simulated)"
        comparison_table_rows.append(row_data)

    # Simulate data for comparison charts (e.g., technology mix bar chart)
    techs = ["Solar PV", "Onshore Wind", "Battery Storage", "Hydro", "Thermal"]
    simulated_tech_mix_chart_data = {
        "scenarios": scenario_ids_to_compare, "technologies": techs,
        "capacity_mw_data": [[random.randint(500,4000) for _ in scenario_ids_to_compare] for _ in techs] # MW per tech per scenario
    }

    return jsonify({
        "success": True,
        "message": "Scenario comparison data generated successfully (simulated).",
        "data": {
            "comparison_table_data": comparison_table_rows,
            "comparison_charts_data": {
                "technology_mix_comparison_mw": simulated_tech_mix_chart_data,
                "cost_evolution_comparison_inr": {"note": "Cost evolution chart data would go here (simulated)."},
                "reliability_metrics_comparison": {"note": "Reliability metrics (LOLP, EENS) chart data would go here (simulated)."}
            }
        }
    }), 200
