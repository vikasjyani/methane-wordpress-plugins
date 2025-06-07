"""
API routes for Helper Pages features.

This blueprint handles API endpoints related to helper functionalities,
such as listing available templates for download.
It uses `current_app.config` to access the list of defined templates.
"""
from flask import Blueprint, jsonify, current_app

# Blueprint for helper pages API, prefixed with /api/helpers
helpers_bp = Blueprint('helpers_api', __name__, url_prefix='/api/helpers')

@helpers_bp.route('/templates_list', methods=['GET'])
def api_get_downloadable_templates_list(): # Renamed for clarity
    """
    API endpoint to retrieve a list of available downloadable templates.
    Templates are defined in `current_app.config['TEMPLATES_LIST']`.

    Returns:
        JSON: Success status, message, and a list of template metadata objects.
    """
    templates_list = current_app.config.get('TEMPLATES_LIST', [])

    # Add any processing here if needed, e.g., checking file existence if links were dynamic
    # For this simulation, the list is returned as is.

    return jsonify({
        "success": True,
        "data": templates_list,
        "message": "List of available downloadable templates fetched successfully."
    }), 200

# Potential future helper APIs:
# @helpers_bp.route('/user_guide_search', methods=['GET'])
# def api_search_user_guide():
#     query = request.args.get('q')
#     # Simulate search logic against user guide content
#     results = [{"title": f"Search result for {query}", "excerpt": "...", "link": "#"}]
#     return jsonify({"success": True, "data": results})

# @helpers_bp.route('/submit_feedback', methods=['POST'])
# def api_submit_feedback():
#     feedback_data = request.json
#     # Simulate storing feedback
#     print(f"Feedback received: {feedback_data}")
#     return jsonify({"success": True, "message": "Feedback submitted successfully (simulated)."}), 201
