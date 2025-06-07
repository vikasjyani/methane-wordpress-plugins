"""
API routes for Admin Panel features.

This blueprint handles API endpoints related to managing platform features (toggles),
monitoring system status (health, metrics, active jobs, error logs), and fetching
performance data for charts. It interacts with `current_app.config` for storing
feature configurations and accessing simulated job statuses.
"""
from flask import Blueprint, request, jsonify, current_app
import time
import random
import datetime

# Blueprint for admin panel API, prefixed with /api/admin
admin_bp = Blueprint('admin_api', __name__, url_prefix='/api/admin')

@admin_bp.route('/features_config', methods=['GET']) # Renamed for clarity
def api_get_admin_features_configuration(): # Renamed for clarity
    """
    API endpoint to retrieve the current configuration of all platform features.
    Feature states are stored in `current_app.config['FEATURES_CONFIG']`.

    Returns:
        JSON: Success status, message, and the features configuration object.
    """
    features_config = current_app.config.get('FEATURES_CONFIG',
                                             {"core": [], "advanced": [], "experimental": []}) # Default if not set
    return jsonify({
        "success": True,
        "data": features_config,
        "message": "Platform features configuration fetched successfully."
    }), 200

@admin_bp.route('/features_config/<feature_id>', methods=['PUT']) # Renamed for clarity
def api_update_admin_feature_status(feature_id):
    """
    API endpoint to update the enabled/disabled status of a single feature.
    Expects JSON data with `{"enabled": True/False}`.
    Updates the feature state in `current_app.config['FEATURES_CONFIG']`.

    Args:
        feature_id (str): The ID of the feature to update.

    Request JSON Body:
        enabled (bool): The new status for the feature (required).

    Returns:
        JSON: Success status and message.
              Returns 400 if 'enabled' status is not provided.
              Returns 404 if feature_id is not found.
    """
    data = request.json
    new_status = data.get('enabled')

    if new_status is None or not isinstance(new_status, bool):
        return jsonify({"success": False, "message": "'enabled' field (boolean) is required."}), 400

    features_config = current_app.config.get('FEATURES_CONFIG', {})
    feature_found_and_updated = False
    for category_key in features_config: # e.g., "core", "advanced"
        for feature_item in features_config[category_key]:
            if feature_item.get('id') == feature_id:
                feature_item['enabled'] = new_status
                feature_found_and_updated = True
                break
        if feature_found_and_updated:
            break

    if feature_found_and_updated:
        current_app.config['FEATURES_CONFIG'] = features_config # Persist change in app.config
        print(f"Admin Action: Feature '{feature_id}' status changed to: {new_status} (Simulated in-memory update)")
        return jsonify({"success": True, "message": f"Feature '{feature_id}' status updated to {new_status} successfully (simulated)."}), 200
    else:
        return jsonify({"success": False, "message": f"Feature with ID '{feature_id}' not found."}), 404

@admin_bp.route('/features_config/apply_all', methods=['POST'])
def api_apply_all_feature_config_changes(): # Renamed for clarity
    """
    API endpoint to apply changes to all feature states at once.
    Expects a JSON payload representing the complete features configuration object.
    Updates `current_app.config['FEATURES_CONFIG']`.

    Request JSON Body:
        (dict): The complete features configuration object, structured by categories
                (e.g., {"core": [{"id": "...", "enabled": ...}], ...}).

    Returns:
        JSON: Success status and message.
              Returns 400 if payload is invalid.
    """
    all_feature_states_payload = request.json

    if not isinstance(all_feature_states_payload, dict) or \
       not all(k in all_feature_states_payload for k in ["core", "advanced", "experimental"]): # Basic check
         return jsonify({"success": False, "message": "Invalid payload format. Expected full features configuration object."}), 400

    current_features_config = current_app.config.get('FEATURES_CONFIG', {}) # Get current config

    # Iterate through the payload and update the config in memory
    for category_key, features_in_payload_list in all_feature_states_payload.items():
        if category_key in current_features_config and isinstance(features_in_payload_list, list):
            for new_feature_state in features_in_payload_list:
                if isinstance(new_feature_state, dict) and 'id' in new_feature_state and 'enabled' in new_feature_state:
                    for existing_feature in current_features_config[category_key]:
                        if existing_feature['id'] == new_feature_state['id']:
                            existing_feature['enabled'] = bool(new_feature_state['enabled']) # Ensure boolean
                            break

    current_app.config['FEATURES_CONFIG'] = current_features_config # Save the entirety of updated config
    print(f"Admin Action: All feature changes applied (Simulated). Current config reflects payload.")
    return jsonify({"success": True, "message": "All feature configuration changes applied successfully (simulated)."}), 200

@admin_bp.route('/system_monitoring_status', methods=['GET']) # Renamed for clarity
def api_get_admin_system_monitoring_status(): # Renamed for clarity
    """
    API endpoint to retrieve current system monitoring status and metrics.
    Includes system health, uptime, resource usage, active jobs, and recent error logs.
    Data is simulated.

    Returns:
        JSON: Success status, message, and a comprehensive system status object.
    """
    simulated_jobs = current_app.config.get('SIMULATED_JOBS', {})
    active_jobs_sample_list = []
    job_ids_list = list(simulated_jobs.keys())
    random.shuffle(job_ids_list) # Get a random sample of jobs
    for job_id in job_ids_list[:5]: # Display up to 5 jobs
        job_details = simulated_jobs[job_id]
        active_jobs_sample_list.append({
            "id": job_id,
            "type": job_details.get("type", "N/A"),
            "user": job_details.get("user", f"user_{random.choice(['Ops','System','Test'])}"),
            "submitted_at": datetime.datetime.fromtimestamp(job_details.get("start_time", time.time()) - random.randint(10,3600)).strftime('%Y-%m-%d %H:%M:%S'), # Simulate submission time
            "progress_percent": round(job_details.get("progress", 0) * 100, 1),
            "current_status_text": job_details.get("current_step", job_details.get("status", "Unknown"))
        })

    # Simulate some recent error logs
    error_logs_sample_list = [
        {"timestamp": (datetime.datetime.now() - datetime.timedelta(minutes=random.randint(1,1440*2))).strftime('%Y-%m-%d %H:%M:%S'), "severity": "ERROR", "module": "PyPSA_Solver", "message": f"Solver error: {random.choice(['License invalid', 'Memory allocation failed', 'Convergence issue'])} (simulated)."},
        {"timestamp": (datetime.datetime.now() - datetime.timedelta(minutes=random.randint(1,1440*2))).strftime('%Y-%m-%d %H:%M:%S'), "severity": "WARNING", "module": "DataUpload", "message": "Uploaded file 'input_data.csv' has inconsistent date formats (simulated)."},
    ]
    if random.choice([True, False]): # Sometimes add an info log
         error_logs_sample_list.append({"timestamp": (datetime.datetime.now() - datetime.timedelta(minutes=random.randint(1,1440*2))).strftime('%Y-%m-%d %H:%M:%S'), "severity": "INFO", "module": "UserAuth", "message": "Admin user 'kseb_admin' logged in from IP 10.0.1.5 (simulated)."})

    system_status_data = {
        "overall_system_health": {"status_text": "Healthy", "last_checked_at": datetime.datetime.now().isoformat()},
        "platform_uptime_stats": {"percentage": round(random.uniform(99.95, 99.999), 3), "since_datetime": (datetime.datetime.now() - datetime.timedelta(days=random.randint(5,45))).isoformat()},
        "current_active_sessions": random.randint(2, 20),
        "jobs_in_queue_count": len([j_id for j_id, j_val in simulated_jobs.items() if j_val.get("status") == "queued"]),
        "resource_utilization": {
            "cpu_usage_percent": random.randint(8, 55), "cpu_load_average_1m": round(random.uniform(0.1, 2.5), 2),
            "memory_usage_percent": random.randint(30, 70), "memory_used_gb": round(random.uniform(5.0, 13.0),1), "memory_total_gb": 16.0,
            "disk_io_summary": {"read_mbps": round(random.uniform(0.1, 12),1), "write_mbps": round(random.uniform(0.1, 6),1)},
            "database_connections": {"active": random.randint(8, 35), "max_allowed": 150},
        },
        "active_jobs_list": active_jobs_sample_list,
        "recent_error_logs": error_logs_sample_list[:10] # Show up to 10 recent logs
    }
    return jsonify({"success": True, "data": system_status_data, "message": "Current system monitoring status fetched (simulated)."}), 200

@admin_bp.route('/performance_chart_data/<chart_type>', methods=['GET']) # Renamed for clarity
def api_get_admin_performance_chart_data(chart_type): # Renamed for clarity
    """
    API endpoint to retrieve time-series data for performance monitoring charts.
    Args:
        chart_type (str): Type of chart data requested (e.g., "api_response", "db_query").

    Returns:
        JSON: Success status, message, and data for the requested performance chart.
              Returns 400 if chart_type is invalid.
    """
    num_data_points = 20 # e.g., last 20 data points (could represent minutes, 5-min intervals, etc.)
    base_time = datetime.datetime.now()
    timestamps_iso = [(base_time - datetime.timedelta(minutes=(num_data_points-1-i)*5)).isoformat() for i in range(num_data_points)]

    chart_values = []
    chart_label = ""
    if chart_type == "api_response_time": # Matched JS usage
        chart_values = [random.randint(15, 300) for _ in range(num_data_points)] # Milliseconds
        chart_label = "Average API Response Time (ms)"
    elif chart_type == "db_query_time": # Matched JS usage
        chart_values = [random.randint(1, 70) for _ in range(num_data_points)] # Milliseconds
        chart_label = "Average Database Query Time (ms)"
    elif chart_type == "job_throughput":
        chart_values = [random.randint(0,10) for _ in range(num_data_points)] # Jobs per 5-min interval
        chart_label = "Job Throughput (jobs/5min)"
    else:
        return jsonify({"success": False, "message": f"Invalid chart type '{chart_type}' specified."}), 400

    return jsonify({
        "success": True,
        "data": {
            "chart_type_requested": chart_type,
            "chart_label": chart_label,
            "timestamps_iso": timestamps_iso,
            "values": chart_values
        },
        "message": f"Performance chart data for '{chart_label}' fetched successfully (simulated)."
    }), 200
