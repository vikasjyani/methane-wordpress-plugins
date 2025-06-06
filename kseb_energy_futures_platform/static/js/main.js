// Main JavaScript file for KSEB Energy Futures Platform
console.log("KSEB Energy Futures Platform JS Loaded");

// --- Helper Functions --- (Assume these exist from previous steps)
function displayStatusMessage(elementId, message, isError = false, isSuccess = false) { /* ... */ }
function updateRangeValue(rangeInputId, displayElementSelector) { /* ... */ }

// --- Home Page & Other Module Functions --- (Assume these exist and are collapsed for brevity)
/* ... All functions from Project Management, Demand Projection, Load Profile, PyPSA Modeling, PyPSA Results, Admin Panel ... */
/* ... Generic Job Polling Function (pollJobStatus) ... */


// --- Helper Pages Module Functions ---

// User Guide Page
function setupUserGuideTOC() {
    const tocLinks = document.querySelectorAll('#user-guide-toc-list a');
    const contentArea = document.getElementById('guide-content-main');
    const contentTitle = document.getElementById('guide-content-title');

    if (!tocLinks.length || !contentArea || !contentTitle) return;

    tocLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const topicId = link.dataset.topic;
            const topicName = link.textContent;

            // Remove active class from all links
            tocLinks.forEach(l => l.classList.remove('active-topic'));
            // Add active class to clicked link
            link.classList.add('active-topic');

            contentTitle.textContent = topicName;
            // Placeholder content update - in a real app, this might fetch content
            contentArea.innerHTML = `
                <h4>Content for: ${topicName}</h4>
                <p>This is placeholder content for the topic "${topicName}" (ID: ${topicId}).</p>
                <p>Detailed information, instructions, and examples related to ${topicName} would be displayed here. For example, if this were "Creating a New Project", it would explain all the fields and steps involved in that process.</p>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
            `;
            console.log(`User guide topic changed to: ${topicName} (ID: ${topicId})`);
        });
    });
}

// Template Download Page
async function fetchAndPopulateTemplatesTable() {
    const tbody = document.getElementById('available-templates-tbody');
    if (!tbody) return; // Only on templates download page

    console.log("Fetching available templates list...");
    try {
        const response = await fetch('/api/helpers/templates_list');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const data = await response.json();

        tbody.innerHTML = ''; // Clear existing static or previously fetched rows
        if (data.templates && data.templates.length > 0) {
            data.templates.forEach(template => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td><a href="#" class="template-doc-link" data-template-id="${template.id}">${template.name}</a></td>
                    <td>${template.category}</td>
                    <td>${template.description}</td>
                    <td>${template.file_type}</td>
                    <td>${template.version}</td>
                    <td><a href="${template.download_link}" class="btn-primary btn-small download-template-btn" data-template-id="${template.id}" download>Download</a></td>
                `;
                // Add listener for template name link to show docs
                row.querySelector('.template-doc-link').addEventListener('click', (e) => {
                    e.preventDefault();
                    displayTemplateDocumentation(template);
                });
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6">No templates currently available.</td></tr>';
        }
    } catch (error) {
        console.error("Error fetching templates list:", error);
        tbody.innerHTML = '<tr><td colspan="6">Error loading templates. Please try again later.</td></tr>';
    }
}

function displayTemplateDocumentation(template) {
    const docContentArea = document.getElementById('template-doc-content');
    if (!docContentArea) return;

    docContentArea.innerHTML = `
        <h4>Documentation for: ${template.name} (v${template.version})</h4>
        <p><strong>Category:</strong> ${template.category}</p>
        <p><strong>Description:</strong> ${template.description}</p>
        <p><strong>File Type:</strong> ${template.file_type}</p>
        <p><strong>Usage Notes:</strong></p>
        <ul>
            <li>Ensure data is entered starting from the second row, as the first row usually contains headers.</li>
            <li>Follow the specified date/time formats if applicable.</li>
            <li>Do not change column headers.</li>
            <li>(More specific placeholder instructions for ${template.name} would go here...)</li>
        </ul>
        <p><a href="${template.download_link}" class="btn-secondary btn-small" download>Download ${template.name}</a></p>
    `;
    console.log(`Displaying documentation for template: ${template.name}`);
}


// --- DOMContentLoaded Event Listener ---
document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM fully loaded and parsed. Setting up event listeners for all modules.");

    // Common elements & Home Page (Assume setup from previous steps)
    if (document.getElementById('recent-projects-list')) {
        // fetchRecentProjectsForHomePage(); // Example call
        // fetchRecentActivities(); // Example call
    }
    // ... other common listeners for navigation, project management, etc. ...
    // Ensure functions like fetchRecentProjectsForHomePage are fully defined if called.

    // Admin Panel: (Assume setup from previous steps)
    if (document.getElementById('core-features-table')) { /* fetchAdminFeatures(); ... */ }
    if (document.getElementById('system-health-status')) { /* fetchAdminSystemStatus(); ... */ }

    // PyPSA Results Visualization: (Assume setup from previous steps)
    if (document.getElementById('pypsa-available-results-table')) { /* fetchPyPSAavailableResults(); ... */ }
    if (document.querySelector('.results-dashboard-container .results-tabs-nav')) { /* setupResultsDashboardTabs(); ... */ }
    if (document.getElementById('pypsa-scenario-checkboxes')) { /* ... comparison page listeners ... */ }

    // Helper Pages
    // User Guide Page
    if (document.getElementById('user-guide-toc-list')) {
        setupUserGuideTOC();
        document.getElementById('user-guide-download-pdf-btn')?.addEventListener('click', () => alert('Download User Guide as PDF: Not implemented.'));
        document.getElementById('user-guide-print-btn')?.addEventListener('click', () => alert('Print User Guide: Not implemented. This would typically use window.print().'));
        document.getElementById('user-guide-feedback-btn')?.addEventListener('click', () => alert('Provide Feedback: Not implemented. This might open a survey or email client.'));
    }

    // Template Download Page
    if (document.getElementById('available-templates-table')) {
        fetchAndPopulateTemplatesTable(); // Fetch templates via API
        // Event listeners for download buttons are implicitly handled by 'download' attribute
        // or could be added here if more complex logic (e.g., logging) is needed.
        // Example:
        // document.getElementById('available-templates-tbody').addEventListener('click', function(event) {
        //     if (event.target.classList.contains('download-template-btn')) {
        //         const templateId = event.target.dataset.templateId;
        //         console.log(`Download button clicked for template: ${templateId}`);
        //         // Actual download is handled by <a> tag's href and download attribute.
        //         // Could add analytics or logging here.
        //     }
        // });
    }

    // Ensure all other module-specific DOMContentLoaded listeners are also processed.
    // For brevity, they are not repeated here but would be part of a unified main.js.
});

// Ensure all placeholder functions from previous steps are defined or their logic integrated.
// For brevity, many are marked with /* ... */ assuming they are complete from prior steps.
// This is crucial for the application to function as a whole.
// Example stubs for functions assumed to be defined elsewhere:
function displayStatusMessage(elementId, message, isError = false, isSuccess = false) { /* ... */ }
function updateRangeValue(rangeInputId, displayElementSelector = '.range-value') { /* ... */ }
async function pollJobStatus(jobId, progressBarId, statusElementId, modalId = null) { /* ... */ }
// And all module-specific functions like fetchRecentProjectsForHomePage, fetchAdminFeatures, etc.
// These should be fully implemented in the actual file. The /* ... */ is just for this diff context.
