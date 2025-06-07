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
# These dictionaries and lists simulate a database or persistent storage for this prototype.

# Simulated list of recent projects. Each project is a dictionary.
app.config['RECENT_PROJECTS'] = [
    {"id": "proj_hyw_001", "name": "Project HydroWave", "path": "/sim/path/Project_HydroWave", "last_modified": "2024-07-28T10:00:00Z", "description": "Focuses on hydropower potential...", "created": "2024-07-27T00:00:00Z"},
    {"id": "proj_slf_002", "name": "Project SolarFlare", "path": "/sim/path/Project_SolarFlare", "last_modified": "2024-07-27T15:30:00Z", "description": "Large scale solar PV analysis.", "created": "2024-07-26T00:00:00Z"},
]

# Simulated list of demand scenarios. Each scenario is a dictionary.
app.config['DEMAND_SCENARIOS'] = [
    {"id": "Baseline_2040", "name": "Baseline Forecast to 2040", "details": "Standard growth assumptions."},
    {"id": "HighGrowth_EV_2040", "name": "High Growth (EV Focus) to 2040", "details": "Accelerated EV adoption."},
]

# Simulated list of generated load profiles metadata.
app.config['LOAD_PROFILES'] = [
    {"id": "lp_job_1620000000", "name": "Baseline_Hourly_2025-2030_MW", "demand_scenario_id": "Baseline_2040", "generated_at": "2024-08-10T10:30:00Z"},
]

# Simulated list of PyPSA scenario results metadata.
app.config['PYPSA_SCENARIOS'] = [
    {"id": "CoOpt_Baseline_20240810", "name": "CoOpt_Baseline_20240810", "created": "2024-08-10T15:30:00Z", "status": "Completed", "load_profile_id": "lp_job_1620000000", "description": "Baseline run", "size_mb": 120},
    {"id": "CoOpt_HighRE_20240811", "name": "CoOpt_HighRE_20240811", "created": "2024-08-11T10:00:00Z", "status": "Completed", "load_profile_id": "lp_job_1620000000", "description": "High RE scenario", "size_mb": 150},
]

# Configuration for feature toggles in the Admin Panel.
app.config['FEATURES_CONFIG'] = {
    "core": [
        {"id": "project_management", "name": "Project Management", "enabled": True, "description": "Core project creation, load, and save functionalities."},
        {"id": "demand_projection", "name": "Demand Projection Module", "enabled": True, "description": "Module for forecasting future electricity demand."},
        {"id": "load_profile_generation", "name": "Load Profile Generation", "enabled": True, "description": "Synthesize load profiles from demand data."},
    ],
    "advanced": [
        {"id": "pypsa_modeling", "name": "PyPSA Co-optimisation", "enabled": True, "description": "Perform generation and transmission capacity expansion planning."},
        {"id": "pypsa_results_viz", "name": "PyPSA Results Visualization", "enabled": True, "description": "Visualize and analyze PyPSA simulation outputs."},
        {"id": "lp_ml_methods", "name": "Load Profile (ML Methods)", "enabled": False, "description": "Use Machine Learning for advanced load profile synthesis."}
    ],
    "experimental": [
        {"id": "realtime_coopt", "name": "Real-time Co-optimisation", "enabled": False, "description": "Experimental feature for near real-time market simulation."},
        {"id": "ai_scenario_gen", "name": "AI-Assisted Scenario Generation", "enabled": False, "description": "Use AI to suggest plausible future scenarios."}
    ]
}

# Dictionary to store status and progress of simulated background jobs.
app.config['SIMULATED_JOBS'] = {}

# List of downloadable templates for helper pages.
app.config['TEMPLATES_LIST'] = [
    {"id": "demand_input_v2.1", "name": "Demand Input File", "category": "Demand Projection", "description": "Standard format for historical demand data.", "file_type": "Excel (.xlsx)", "version": "2.1", "download_link": "/static/templates/Sample_Demand_Input_v2.1.xlsx"},
    {"id": "load_curve_8760_v1.5", "name": "Load Curve Template", "category": "Load Profile", "description": "8760 hourly load points for a typical year.", "file_type": "CSV (.csv)", "version": "1.5", "download_link": "/static/templates/Sample_Load_Curve_8760_v1.5.csv"},
    {"id": "pypsa_network_kseb_v2.1", "name": "PyPSA Network Data", "category": "PyPSA Modeling", "description": "Base network data for PyPSA.", "file_type": "Excel (.xlsx)", "version": "KSEB_Ref_v2.1", "download_link": "/static/templates/Sample_PyPSA_Network_KSEB_v2.1.xlsx"},
    {"id": "tech_cost_2024_v1.2", "name": "Technology Cost File", "category": "PyPSA Modeling", "description": "Technology cost and performance assumptions.", "file_type": "Excel (.xlsx)", "version": "TechData_2024_v1.2", "download_link": "/static/templates/Sample_Tech_Cost_2024_v1.2.xlsx"},
    {"id": "report_format_v1.0", "name": "Reporting Format", "category": "General", "description": "Standard MS Word template for project reports.", "file_type": "Word (.docx)", "version": "1.0", "download_link": "/static/templates/Sample_Report_Format_v1.0.docx"}
]

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

# Project Management Pages
@app.route('/project/create', methods=['GET'])
def create_project_page():
    """Renders the page for creating a new project."""
    return render_template('project/create_project.html')

@app.route('/project/load', methods=['GET'])
def load_project_page():
    """Renders the page for loading an existing project."""
    return render_template('project/load_project.html')

# Demand Projection Pages
@app.route('/demand_projection', methods=['GET'])
def demand_projection_data_input_page():
    """Renders the data input and configuration page for demand projection."""
    return render_template('demand_projection/data_input.html')

@app.route('/demand_projection/visualize', methods=['GET'])
def demand_projection_visualize_page():
    """Renders the visualization page for demand projection results."""
    return render_template('demand_projection/visualization.html')

# Load Profile Generation Pages
@app.route('/load_profile/create', methods=['GET'])
def load_profile_creation_page():
    """Renders the page for creating and configuring load profiles."""
    return render_template('load_profile/creation.html')

@app.route('/load_profile/analyze', methods=['GET'])
def load_profile_analysis_page():
    """Renders the page for analyzing generated load profiles."""
    return render_template('load_profile/analysis.html')

# PyPSA Modeling Pages
@app.route('/pypsa_modeling/configure', methods=['GET'])
def pypsa_model_configuration_page():
    """Renders the page for configuring PyPSA model scenarios."""
    return render_template('pypsa_modeling/configuration.html')

# PyPSA Results Visualization Pages
@app.route('/pypsa_results', methods=['GET'])
def pypsa_results_selection_page():
    """Renders the page for selecting PyPSA simulation results to view."""
    return render_template('pypsa_results/selection.html')

@app.route('/pypsa_results/dashboard/<scenario_id>', methods=['GET'])
def pypsa_results_dashboard_page(scenario_id):
    """
    Renders the dashboard for visualizing a specific PyPSA scenario's results.
    Args:
        scenario_id (str): The ID of the PyPSA scenario to display.
    """
    return render_template('pypsa_results/dashboard.html', scenario_id=scenario_id)

@app.route('/pypsa_results/compare', methods=['GET'])
def pypsa_results_compare_page():
    """Renders the page for comparing multiple PyPSA scenarios."""
    return render_template('pypsa_results/compare.html')

# Admin Panel Pages
@app.route('/admin/features', methods=['GET'])
def admin_feature_management_page():
    """Renders the admin page for managing platform features."""
    return render_template('admin/feature_management.html')

@app.route('/admin/monitoring', methods=['GET'])
def admin_system_monitoring_page():
    """Renders the admin page for system monitoring and health checks."""
    return render_template('admin/system_monitoring.html')

# Helper Pages
@app.route('/user_guide', methods=['GET'])
def user_guide_page():
    """Renders the user guide page."""
    return render_template('helpers/user_guide.html')

@app.route('/templates_download', methods=['GET'])
def templates_download_page():
    """Renders the page for downloading data templates."""
    # Templates list is passed directly from app.config for initial rendering.
    # An API also exists if JS-driven population is preferred later.
    return render_template('helpers/templates_download.html', static_templates=app.config.get('TEMPLATES_LIST', []))


# --- Generic API Endpoints ---
@app.route('/api/job_status/<job_id>', methods=['GET'])
def api_get_job_status(job_id):
    """
    Generic API endpoint to get the status of a simulated background job.
    Args:
        job_id (str): The ID of the job to check.
    Returns:
        JSON: Job status information including progress, current step, and type.
              Returns 404 if job ID is not found.
    """
    job = app.config.get('SIMULATED_JOBS', {}).get(job_id)
    if not job:
        return jsonify({"success": False, "message": "Job ID not found."}), 404

    # Simulate job progress if it's still running
    if job["status"] == "queued":
        job["status"] = "running"
        job["progress"] = 0.05
        job["start_time"] = job.get("start_time", time.time()) # Ensure start_time exists
        job["current_step"] = "Initializing job..."
    elif job["status"] == "running":
        current_progress = job.get("progress", 0)
        # Determine increment based on job type or a default
        increment_factor = 0.1 # Default increment
        if job.get("type") == "pypsa":
            increment_factor = 0.05 # PyPSA jobs progress slower
        elif job.get("type") == "forecast":
             increment_factor = 0.15 # Forecasts might be quicker

        job["progress"] = min(1.0, current_progress + random.uniform(increment_factor * 0.5, increment_factor * 1.5))

        # Update current_step based on progress (example logic)
        num_steps = 5 # Assume 5 major steps for any job for simulation
        current_major_step = int(job['progress'] * num_steps)
        if job['progress'] < 1.0:
            job["current_step"] = f"Processing step {current_major_step + 1} of {num_steps}..."
        else:
            job["current_step"] = "Finalizing results..."

    # Mark as completed if progress is full
    if job.get("progress",0) >= 1.0 and job["status"] == "running":
        job["status"] = "completed"
        job["results_path"] = f"/simulated_results/data/{job_id}_output.zip" # Example path
        job["current_step"] = "Job completed successfully."

    return jsonify({
        "success": True,
        "job_id": job_id,
        "type": job.get("type", "unknown_job"),
        "status": job["status"],
        "progress": round(job.get("progress",0),2),
        "results_path": job.get("results_path"),
        "current_step": job.get("current_step", job["status"]),
        "message": f"Status for job {job_id} fetched."
    }), 200


if __name__ == '__main__':
    app.run(debug=True)
