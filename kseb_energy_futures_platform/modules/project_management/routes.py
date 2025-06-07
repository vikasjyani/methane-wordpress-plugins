"""
API routes for Project Management features.

This blueprint handles API endpoints related to creating, loading,
listing recent projects, and validating project structures.
Project data is simulated and stored in memory via `current_app.config`.
"""
from flask import Blueprint, request, jsonify, current_app
import datetime
import uuid # For generating unique project IDs

# Blueprint for project management API, prefixed with /api/project
project_bp = Blueprint('project_api', __name__, url_prefix='/api/project')

def _add_to_recent_projects(project_data):
    """
    Helper function to add or update a project in the list of recent projects.
    The list is stored in `current_app.config['RECENT_PROJECTS']`.
    It keeps a maximum of 5 recent projects, removing the oldest if the limit is exceeded.
    If a project with the same path already exists, its details (like last_modified) are updated.

    Args:
        project_data (dict): A dictionary containing project details, must include 'path' and 'last_modified'.
                             Typically also includes 'id', 'name', 'description'.
    """
    recent_projects = current_app.config.get('RECENT_PROJECTS', [])

    existing_project_index = -1
    for i, p in enumerate(recent_projects):
        if p.get('path') == project_data.get('path'):
            existing_project_index = i
            break

    if existing_project_index != -1:
        # Project exists, update its last_modified timestamp and potentially other details
        recent_projects[existing_project_index].update(project_data)
        # Move to the end (most recent) by removing and re-appending
        updated_project_entry = recent_projects.pop(existing_project_index)
        recent_projects.append(updated_project_entry)
    else:
        # New project, add to the list
        recent_projects.append(project_data)
        # If list exceeds max size, remove the oldest (first element)
        while len(recent_projects) > 5: # Max 5 recent projects
            recent_projects.pop(0)

    current_app.config['RECENT_PROJECTS'] = recent_projects


@project_bp.route('/create', methods=['POST'])
def api_create_project():
    """
    API endpoint to simulate the creation of a new project.
    Expects JSON data with 'projectName' and optionally other details.
    Adds the new project to the in-memory list of recent projects.

    Request JSON Body:
        projectName (str): Name of the project (required).
        projectDescription (str, optional): Description of the project.
        projectLocation (str, optional): Base path for the project.
        projectType (str, optional): Type of the project (e.g., 'wind_power').
        initializeSampleData (bool, optional): Flag to initialize with sample data.
        copySettingsFrom (str, optional): ID of an existing project to copy settings from.
        templatesToInclude (list, optional): List of template IDs to include.

    Returns:
        JSON: Success status, message, and details of the created project (including a simulated ID and path).
              Returns 400 if 'projectName' is missing.
    """
    data = request.json
    if not data or not data.get('projectName'):
        return jsonify({"success": False, "message": "Project name ('projectName') is required."}), 400

    project_name = data.get('projectName')
    project_id = f"proj_{str(uuid.uuid4())[:8]}" # Generate a short unique ID

    base_path = data.get('projectLocation', '/simulated_projects').strip()
    if not base_path:
        base_path = '/simulated_projects' # Default path if empty string provided
    project_path = f"{base_path}/{project_name.replace(' ', '_')}" # Create a slug-like path

    new_project_data = {
        "id": project_id,
        "name": project_name,
        "description": data.get('projectDescription', ''),
        "path": project_path,
        "project_type": data.get('projectType', 'custom'),
        "created_at": datetime.datetime.now().isoformat(),
        "last_modified": datetime.datetime.now().isoformat(),
        "config_details": { # Store other submitted details
            "initializeSampleData": data.get('initializeSampleData', False),
            "copySettingsFrom": data.get('copySettingsFrom'),
            "templatesToInclude": data.get('templatesToInclude', [])
        }
    }

    # Add to recent projects list (helper manages size and updates)
    _add_to_recent_projects({
        "id": new_project_data["id"], "name": new_project_data["name"], "path": new_project_data["path"],
        "last_modified": new_project_data["last_modified"], "description": new_project_data["description"]
    })

    # current_app.config.get('ALL_PROJECTS_LIST', []).append(new_project_data) # If maintaining a global list of all projects

    return jsonify({
        "success": True,
        "message": f"Project '{project_name}' creation simulated successfully.",
        "data": new_project_data # Return full details of the created project
    }), 201

@project_bp.route('/load', methods=['POST'])
def api_load_project():
    """
    API endpoint to simulate loading an existing project.
    Expects JSON data with 'projectPath'.
    If found in recent projects, its 'last_modified' is updated.
    Otherwise, simulates loading a new project from the given path.

    Request JSON Body:
        projectPath (str): Full path to the project to be loaded (required).

    Returns:
        JSON: Success status, message, and details of the loaded project.
              Returns 400 if 'projectPath' is missing.
    """
    data = request.json
    project_path = data.get('projectPath')

    if not project_path:
        return jsonify({"success": False, "message": "Project path ('projectPath') is required."}), 400

    recent_projects = current_app.config.get('RECENT_PROJECTS', [])
    project_details = next((p for p in recent_projects if p["path"] == project_path), None)

    current_time_iso = datetime.datetime.now().isoformat()

    if not project_details:
        # Simulate finding/loading a project not in recent list
        project_details = {
            "id": f"proj_loaded_{str(uuid.uuid4())[:4]}",
            "name": project_path.split('/')[-1] or "Path Loaded Project",
            "path": project_path,
            "description": "Project loaded by path (simulated details).",
            "last_modified": current_time_iso, # Set last_modified to now
            "created_at": (datetime.datetime.now() - datetime.timedelta(days=random.randint(1,30))).isoformat() # Simulate past creation
        }
    else:
        # Update last_modified for existing recent project
        project_details["last_modified"] = current_time_iso

    _add_to_recent_projects(project_details.copy()) # Ensure it's in recent list and updated

    # Simulate loading additional project-specific configuration data
    simulated_project_config_data = {
        "config_version": "1.1_sim",
        "linked_demand_scenario_id": "Baseline_2040", # Example
        "linked_load_profile_id": "lp_job_1620000000",  # Example
        "simulation_years": [2025, 2030, 2035], # Example
        "technologies_considered": ["Solar_PV", "Wind_Onshore", "Battery_Storage"] # Example
    }

    # Prepare data to return
    response_data = project_details.copy()
    response_data['project_specific_config'] = simulated_project_config_data

    return jsonify({
        "success": True,
        "message": f"Project '{response_data.get('name')}' load simulated successfully.",
        "data": response_data
    }), 200

@project_bp.route('/recent_projects', methods=['GET'])
def api_get_recent_projects():
    """
    API endpoint to retrieve the list of recent projects.
    Projects are stored in `current_app.config['RECENT_PROJECTS']`.

    Returns:
        JSON: Success status and a list of recent project objects (most recent first).
    """
    recent_projects = current_app.config.get('RECENT_PROJECTS', [])
    # Return in reverse chronological order (most recent is last in list due to append)
    return jsonify({"success": True, "data": recent_projects[::-1], "message": "Recent projects fetched."}), 200

@project_bp.route('/validate', methods=['POST'])
def api_validate_project_structure(): # Renamed for clarity from generic 'validate_project'
    """
    API endpoint to simulate validation of a project's structure and files.
    Expects JSON data with 'projectPath'.

    Request JSON Body:
        projectPath (str): Full path to the project to be validated (required).

    Returns:
        JSON: Success status, message, and simulated validation details.
              Returns 400 if 'projectPath' is missing.
    """
    data = request.json
    project_path = data.get('projectPath')

    if not project_path:
        return jsonify({"success": False, "message": "Project path ('projectPath') is required for validation."}), 400

    # Simulate validation logic based on path content or other factors
    is_valid_structure = "invalid" not in project_path.lower()

    validation_details = {}
    if is_valid_structure:
        validation_details = {
            "config_file_found": True,
            "data_folder_exists": True,
            "load_profile_linked": random.choice([True, False]),
            "demand_data_valid_format": True,
            "pypsa_network_files_ok": random.choice([True, True, False]) if "pypsa" in project_path.lower() else True,
            "warnings": [] if random.choice([True, False]) else ["Minor issue: Cost data seems outdated (simulated)."]
        }
    else: # If structure is invalid
         validation_details["errors"] = ["Project configuration file missing (simulated).", "Key data directories not found (simulated)."]

    message = f"Project '{project_path}' validation "
    message += "completed." if is_valid_structure else "failed (simulated)."

    return jsonify({
        "success": True, # API call itself succeeded, result of validation is in 'is_valid'
        "message": message,
        "data": {
            "projectPath": project_path,
            "is_valid": is_valid_structure,
            "validation_details": validation_details,
            "validated_at": datetime.datetime.now().isoformat()
        }
    }), 200
