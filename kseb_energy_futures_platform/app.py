from flask import Flask, render_template, request, jsonify
import time
import random

app = Flask(__name__)

# --- Dummy Data Stores --- (Collapsed for brevity)
DUMMY_RECENT_PROJECTS = [{"id": "proj_hyw_001", "name": "Project HydroWave", "path": "/sim/path/Project_HydroWave", "last_modified": "2024-07-28T10:00:00Z"}]
SIMULATED_JOBS = {}
DUMMY_DEMAND_SCENARIOS = [{"id": "Baseline_2040", "name": "Baseline Forecast to 2040"}]
DUMMY_PYPSA_RESULTS = [{"id": "CoOpt_Baseline_20240810", "name": "CoOpt_Baseline_20240810", "created": "2024-08-10 15:30", "status": "Completed", "load_profile": "Baseline_Hourly_2025-2030_MW", "description": "Baseline run", "size_mb": 120}]
DUMMY_FEATURES_CONFIG = { "core": [], "advanced": [], "experimental": [] } # Simplified for brevity
DUMMY_TEMPLATES_LIST = [
    {"id": "demand_input_v2.1", "name": "Demand Input File", "category": "Demand Projection", "description": "Standard format for historical demand data (sector-wise, monthly/hourly).", "file_type": "Excel (.xlsx)", "version": "2.1", "download_link": "/static/templates/Sample_Demand_Input_v2.1.xlsx"},
    {"id": "load_curve_8760_v1.5", "name": "Load Curve Template", "category": "Load Profile", "description": "8760 hourly load points for a typical year.", "file_type": "CSV (.csv)", "version": "1.5", "download_link": "/static/templates/Sample_Load_Curve_8760_v1.5.csv"},
    {"id": "pypsa_network_kseb_v2.1", "name": "PyPSA Network Data", "category": "PyPSA Modeling", "description": "Base network data including buses, lines, transformers, and generator types.", "file_type": "Excel (.xlsx)", "version": "KSEB_Ref_v2.1", "download_link": "/static/templates/Sample_PyPSA_Network_KSEB_v2.1.xlsx"},
    {"id": "tech_cost_2024_v1.2", "name": "Technology Cost File", "category": "PyPSA Modeling", "description": "Assumptions for capital costs, FOM, VOM, lifetime, efficiency for various technologies.", "file_type": "Excel (.xlsx)", "version": "TechData_2024_v1.2", "download_link": "/static/templates/Sample_Tech_Cost_2024_v1.2.xlsx"},
    {"id": "report_format_v1.0", "name": "Reporting Format", "category": "General", "description": "Standard MS Word template for project reports and summaries.", "file_type": "Word (.docx)", "version": "1.0", "download_link": "/static/templates/Sample_Report_Format_v1.0.docx"}
]


@app.route('/')
def index(): return render_template('index.html')

# --- Other Module Routes & APIs --- (Collapsed for brevity)
# Project Management, Demand Projection, Load Profile, PyPSA Modeling, PyPSA Results, Admin Panel
# ... (all previous routes and API endpoints are assumed to be here) ...
@app.route('/project/create', methods=['GET'])
def create_project_page(): return render_template('project/create_project.html')
@app.route('/demand_projection', methods=['GET'])
def demand_projection_data_input_page(): return render_template('demand_projection/data_input.html')
@app.route('/load_profile/create', methods=['GET'])
def load_profile_creation_page(): return render_template('load_profile/creation.html')
@app.route('/pypsa_modeling/configure', methods=['GET'])
def pypsa_model_configuration_page(): return render_template('pypsa_modeling/configuration.html')
@app.route('/pypsa_results', methods=['GET'])
def pypsa_results_selection_page(): return render_template('pypsa_results/selection.html')
@app.route('/pypsa_results/dashboard/<scenario_id>', methods=['GET'])
def pypsa_results_dashboard_page(scenario_id): return render_template('pypsa_results/dashboard.html', scenario_id=scenario_id)
@app.route('/admin/features', methods=['GET'])
def admin_feature_management_page(): return render_template('admin/feature_management.html')
@app.route('/admin/monitoring', methods=['GET'])
def admin_system_monitoring_page(): return render_template('admin/system_monitoring.html')


# --- Helper Pages ---
@app.route('/user_guide', methods=['GET'])
def user_guide_page():
    return render_template('helpers/user_guide.html')

@app.route('/templates_download', methods=['GET'])
def templates_download_page():
    # Templates can be passed directly to the template if static,
    # or fetched via an API if dynamic management is needed later.
    # For this subtask, we'll also provide an API endpoint.
    return render_template('helpers/templates_download.html', static_templates=DUMMY_TEMPLATES_LIST)

@app.route('/api/helpers/templates_list', methods=['GET'])
def api_get_templates_list():
    return jsonify({"templates": DUMMY_TEMPLATES_LIST}), 200


# --- Generic Job Status API --- (Ensure this is correctly defined from previous steps)
@app.route('/api/job_status/<job_id>', methods=['GET'])
def api_get_job_status(job_id):
    job = SIMULATED_JOBS.get(job_id)
    if not job: return jsonify({"success": False, "message": "Job ID not found."}), 404
    # Simplified progress simulation
    if job["status"] == "queued": job["status"] = "running"; job["progress"] = 0.05; job["start_time"] = time.time()
    elif job["status"] == "running":
        current_progress = job.get("progress", 0)
        # Determine increment based on job type or a default
        increment = random.uniform(0.05, 0.1) if job.get("type") == "pypsa" else random.uniform(0.1, 0.2)
        job["progress"] = min(1.0, current_progress + increment)
        job["current_step"] = f"Processing step {int(job['progress']*5)} of 5" if job['progress'] < 1 else "Finalizing"
    if job.get("progress",0) >= 1.0 and job["status"] == "running":
        job["status"] = "completed"; job["results_path"] = f"/sim/results/{job_id}"
    return jsonify({
        "job_id": job_id, "type": job.get("type"), "status": job["status"],
        "progress": round(job.get("progress",0),2),
        "results_path": job.get("results_path"),
        "current_step": job.get("current_step", job["status"])
    }), 200

# All other module APIs (Project Management, Demand Projection, etc.) are assumed to be present.
# For brevity, their full definitions are not repeated here.
# Example:
@app.route('/api/recent_projects', methods=['GET'])
def api_get_recent_projects(): return jsonify(DUMMY_RECENT_PROJECTS), 200
@app.route('/api/admin/features', methods=['GET'])
def api_get_admin_features(): return jsonify(DUMMY_FEATURES_CONFIG), 200
# ... and so on for all other APIs defined in previous steps.


if __name__ == '__main__':
    app.run(debug=True)
