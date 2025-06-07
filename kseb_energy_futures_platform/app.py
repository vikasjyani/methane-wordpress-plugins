"""
Main Flask application file for the KSEB Energy Futures Platform.

This file initializes the Flask app, loads configurations, registers blueprints
for different modules, and defines the main HTML rendering routes for the platform.
API endpoints are primarily handled by the blueprints within their respective modules.
"""
from flask import Flask, render_template
import time
import random
import datetime
import os # Added for UPLOAD_FOLDER path handling

# Blueprint imports
from modules.project_management.routes import project_bp
from modules.demand_projection.routes import demand_projection_bp
from modules.load_profile.routes import load_profile_bp
from modules.pypsa_modeling.routes import pypsa_modeling_bp
from modules.pypsa_results.routes import pypsa_results_bp
from modules.admin.routes import admin_bp
from modules.helpers.routes import helpers_bp

app = Flask(__name__)

# --- In-memory Data Stores Initialization in app.config ---
app.config['RECENT_PROJECTS'] = [
    {"id": "proj_hyw_001", "name": "Project HydroWave", "path": "/sim/path/Project_HydroWave", "last_modified": "2024-07-28T10:00:00Z", "description": "Focuses on hydropower potential...", "created": "2024-07-27T00:00:00Z"},
    {"id": "proj_slf_002", "name": "Project SolarFlare", "path": "/sim/path/Project_SolarFlare", "last_modified": "2024-07-27T15:30:00Z", "description": "Large scale solar PV analysis.", "created": "2024-07-26T00:00:00Z"},
]
app.config['DEMAND_SCENARIOS'] = [
    {"id": "Baseline_2040", "name": "Baseline Forecast to 2040", "details": "Standard growth assumptions."},
    {"id": "HighGrowth_EV_2040", "name": "High Growth (EV Focus) to 2040", "details": "Accelerated EV adoption."},
]
app.config['LOAD_PROFILES'] = [
    {"id": "lp_job_1620000000", "name": "Baseline_Hourly_2025-2030_MW", "demand_scenario_id": "Baseline_2040", "generated_at": "2024-08-10T10:30:00Z"},
]
app.config['PYPSA_SCENARIOS'] = [
    {"id": "CoOpt_Baseline_20240810", "name": "CoOpt_Baseline_20240810", "created": "2024-08-10T15:30:00Z", "status": "Completed", "load_profile_id": "lp_job_1620000000", "description": "Baseline run", "size_mb": 120},
    {"id": "CoOpt_HighRE_20240811", "name": "CoOpt_HighRE_20240811", "created": "2024-08-11T10:00:00Z", "status": "Completed", "load_profile_id": "lp_job_1620000000", "description": "High RE scenario", "size_mb": 150},
]
app.config['FEATURES_CONFIG'] = {
    "core": [
        {"id": "project_management", "name": "Project Management", "enabled": True, "description": "Core project creation, load, and save functionalities."},
        # ... other features
    ],
    "advanced": [ {"id": "pypsa_modeling", "name": "PyPSA Co-optimisation", "enabled": True, "description": "Perform G&T expansion planning."} ],
    "experimental": [ {"id": "realtime_coopt", "name": "Real-time Co-optimisation", "enabled": False, "description": "Experimental feature."} ]
}
app.config['SIMULATED_JOBS'] = {}
app.config['TEMPLATES_LIST'] = [
    {"id": "demand_input_v2.1", "name": "Demand Input File", "category": "Demand Projection", "description": "Standard format for historical demand data.", "file_type": "Excel (.xlsx)", "version": "2.1", "download_link": "/static/templates/Sample_Demand_Input_v2.1.xlsx"},
    # ... other templates
]

# Configuration for file uploads
app.config['UPLOAD_FOLDER'] = os.path.join(app.instance_path, 'uploads')
# Ensure the instance folder and upload folder exist
try:
    os.makedirs(app.instance_path, exist_ok=True)
    os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
except OSError as e:
    print(f"Error creating instance/upload folders: {e}")

# Store for processed demand data from last uploaded file
app.config['PROCESSED_DEMAND_DATA'] = None
# Store for processed load curve template data
app.config['PROCESSED_LOAD_CURVE_DATA'] = None
# Store for processed PyPSA input template data
app.config['PROCESSED_PYPSA_DATA'] = None


# Register Blueprints from different modules
app.register_blueprint(project_bp)
app.register_blueprint(demand_projection_bp)
app.register_blueprint(load_profile_bp)
app.register_blueprint(pypsa_modeling_bp)
app.register_blueprint(pypsa_results_bp)
app.register_blueprint(admin_bp)
app.register_blueprint(helpers_bp)


# --- HTML Rendering Routes ---
@app.route('/')
def index():
    """Renders the main home page of the platform."""
    return render_template('index.html')

# ... (all other HTML rendering routes remain unchanged) ...
@app.route('/project/create', methods=['GET'])
def create_project_page(): return render_template('project/create_project.html')
@app.route('/project/load', methods=['GET'])
def load_project_page(): return render_template('project/load_project.html')
@app.route('/demand_projection', methods=['GET'])
def demand_projection_data_input_page(): return render_template('demand_projection/data_input.html')
@app.route('/demand_projection/visualize', methods=['GET'])
def demand_projection_visualize_page(): return render_template('demand_projection/visualization.html')
@app.route('/load_profile/create', methods=['GET'])
def load_profile_creation_page(): return render_template('load_profile/creation.html')
@app.route('/load_profile/analyze', methods=['GET'])
def load_profile_analysis_page(): return render_template('load_profile/analysis.html')
@app.route('/pypsa_modeling/configure', methods=['GET'])
def pypsa_model_configuration_page(): return render_template('pypsa_modeling/configuration.html')
@app.route('/pypsa_results', methods=['GET'])
def pypsa_results_selection_page(): return render_template('pypsa_results/selection.html')
@app.route('/pypsa_results/dashboard/<scenario_id>', methods=['GET'])
def pypsa_results_dashboard_page(scenario_id): return render_template('pypsa_results/dashboard.html', scenario_id=scenario_id)
@app.route('/pypsa_results/compare', methods=['GET'])
def pypsa_results_compare_page(): return render_template('pypsa_results/compare.html')
@app.route('/admin/features', methods=['GET'])
def admin_feature_management_page(): return render_template('admin/feature_management.html')
@app.route('/admin/monitoring', methods=['GET'])
def admin_system_monitoring_page(): return render_template('admin/system_monitoring.html')
@app.route('/user_guide', methods=['GET'])
def user_guide_page(): return render_template('helpers/user_guide.html')
@app.route('/templates_download', methods=['GET'])
def templates_download_page():
    return render_template('helpers/templates_download.html', static_templates=app.config.get('TEMPLATES_LIST', []))


# --- Generic API Endpoints ---
@app.route('/api/job_status/<job_id>', methods=['GET'])
def api_get_job_status(job_id):
    """Generic API endpoint to get the status of a simulated background job."""
    job = app.config.get('SIMULATED_JOBS', {}).get(job_id)
    if not job:
        return jsonify({"success": False, "message": "Job ID not found."}), 404

    # Simulate job progress (simplified)
    if job["status"] == "queued":
        job["status"] = "running"; job["progress"] = 0.05; job["start_time"] = job.get("start_time", time.time()); job["current_step"] = "Initializing job..."
    elif job["status"] == "running":
        current_progress = job.get("progress", 0)
        increment_factor = 0.1 if job.get("type") != "pypsa" else 0.05
        job["progress"] = min(1.0, current_progress + random.uniform(increment_factor * 0.5, increment_factor * 1.5))
        num_steps = 5
        current_major_step = int(job['progress'] * num_steps)
        job["current_step"] = f"Processing step {current_major_step + 1} of {num_steps}..." if job['progress'] < 1 else "Finalizing results..."

    if job.get("progress",0) >= 1.0 and job["status"] == "running":
        job["status"] = "completed"; job["results_path"] = f"/simulated_results/data/{job_id}_output.zip"; job["current_step"] = "Job completed successfully."

    return jsonify({
        "success": True, "job_id": job_id, "type": job.get("type", "unknown_job"),
        "status": job["status"], "progress": round(job.get("progress",0),2),
        "results_path": job.get("results_path"), "current_step": job.get("current_step", job["status"]),
        "message": f"Status for job {job_id} fetched."
    }), 200


if __name__ == '__main__':
    app.run(debug=True)
