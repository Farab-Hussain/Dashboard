// Dashboard JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all functionality
    initializeModals();
    initializeFilters();
    initializeSearch();
    initializeFileUpload();
    initializeFormSubmissions();
    initializeEditProjectForm();
    initializeBidderForms();
    initializeTypeBasedCompletedField();
    initializeStatusBasedRowColors();
    initializeReasonRowColor();
});

// Modal Management
function initializeModals() {
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            hideModal(e.target.id);
        }
    });
    
    // Close modals when clicking close button
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                hideModal(modal.id);
            }
        });
    });
}

// Show modal function
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // Add event listeners for closing
        const closeBtn = modal.querySelector('.close');
        if (closeBtn) {
            closeBtn.onclick = () => hideModal(modalId);
        }
        
        // Close when clicking outside
        modal.onclick = (e) => {
            if (e.target === modal) {
                hideModal(modalId);
            }
        };
    } else {
        console.error(`Modal with ID '${modalId}' not found`);
    }
}

// Hide modal function
function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
}

// Filter Management
function initializeFilters() {
    // Auto-submit form on filter changes
    const filterForm = document.querySelector('.filter-form');
    if (filterForm) {
        // Only auto-submit on select, date, and time inputs (not text inputs)
        const filterInputs = filterForm.querySelectorAll('select, input[type="date"], input[type="time"]');
        filterInputs.forEach(input => {
            input.addEventListener('change', () => {
                filterForm.submit();
            });
        });
        
        // Text inputs (like search) should not auto-submit
        const textInputs = filterForm.querySelectorAll('input[type="text"]');
        textInputs.forEach(input => {
            // Remove any existing change event listeners
            input.removeEventListener('change', () => {});
        });
        
        // Checkboxes don't auto-submit, they need manual submit
        const checkboxes = filterForm.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Don't auto-submit checkboxes, let user control
            });
        });
    }
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        // Only search on Enter key press
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // Prevent form submission
                
                // Show loading state
                const searchButton = document.querySelector('.filter-form button[type="submit"]');
                if (searchButton) {
                    const originalText = searchButton.innerHTML;
                    searchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
                    searchButton.disabled = true;
                    
                    // Reset button after a short delay
                    setTimeout(() => {
                        searchButton.innerHTML = originalText;
                        searchButton.disabled = false;
                    }, 1000);
                }
                
                const filterForm = this.closest('.filter-form');
                if (filterForm) {
                    filterForm.submit();
                }
            }
        });
        
        // Also add click event for search button if it exists
        const searchButton = document.querySelector('.filter-form button[type="submit"]');
        if (searchButton) {
            searchButton.addEventListener('click', function(e) {
                // Let the form submit normally when search button is clicked
                return true;
            });
        }
    }
    
    // Initialize user search functionality
    const userSearchInput = document.getElementById('userSearch');
    if (userSearchInput) {
        userSearchInput.addEventListener('input', function() {
            filterUsers();
        });
    }
    
    // Initialize bidder search functionality
    const bidderSearchInput = document.getElementById('bidderSearch');
    if (bidderSearchInput) {
        bidderSearchInput.addEventListener('input', function() {
            filterBidders();
        });
    }
}

// File upload functionality
function initializeFileUpload() {
    const addFileBtn = document.getElementById('add-file-btn');
    const fileContainer = document.getElementById('file-upload-container');
    
    if (addFileBtn && fileContainer) {
        addFileBtn.addEventListener('click', function() {
                const newRow = document.createElement('div');
                newRow.className = 'file-upload-row';
                newRow.innerHTML = `
                    <select name="file_types[]" required>
                    <option value="">Select File Type</option>
                        <option value="RFQ">RFQ</option>
                        <option value="RFI">RFI</option>
                        <option value="Rejection">Rejection</option>
                    </select>
                    <input type="file" name="project_files[]" required>
                    <button type="button" class="remove-file">Remove</button>
                `;
            
            fileContainer.appendChild(newRow);
            
            // Add event listener to new remove button
            const removeBtn = newRow.querySelector('.remove-file');
            removeBtn.addEventListener('click', function() {
                newRow.remove();
            });
        });
        
        // Add event listeners to existing remove buttons
        document.querySelectorAll('.remove-file').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.file-upload-row').remove();
            });
        });
    }
}

// Form submissions
function initializeFormSubmissions() {
    // Project form submission
    const projectForm = document.getElementById('add-project-form');
    if (projectForm) {
        projectForm.addEventListener('submit', function(e) {
            // Let the form submit normally
            return true;
        });
    }
    
    // User form submission
    const userForm = document.getElementById('add-user-form');
    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            // Let the form submit normally
            return true;
        });
    }
}

// Toast notification system
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    if (toastContainer) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
        toast.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span class="message">${message}</span>
            <button class="close-btn" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Remove toast after animation
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
}

// Delete project functionality
function deleteProject(projectId) {
    if (confirm('Are you sure you want to delete this project?')) {
        fetch('dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_project&project_id=${projectId}&tab=projects`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Project deleted successfully!', 'success');
                // Reload the page to refresh the projects list
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Error deleting project.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting project.', 'error');
        });
    }
}

// Edit user functionality
function editUser(userId) {
    // Fetch user data and populate the edit modal
    fetch(`includes/functions/user_functions.php?action=get_user&user_id=${userId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('edit_user_id').value = user.id;
                document.getElementById('edit_username').value = user.username;
                document.getElementById('edit_role').value = user.role;
                document.getElementById('edit_status').value = user.is_active || '1';
                showModal('edit-user-modal');
            } else {
                showToast(data.error || 'Error loading user data.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading user data.', 'error');
        });
}

// Filter users functionality
function filterUsers() {
    const searchTerm = document.getElementById('userSearch').value.toLowerCase();
    const roleFilter = document.getElementById('roleFilter').value;
    const rows = document.querySelectorAll('.users-table tbody tr');
    
    rows.forEach((row, index) => {
        // Get username from the div with class 'username'
        const usernameElement = row.querySelector('td:nth-child(2) .username');
        const username = usernameElement ? usernameElement.textContent.toLowerCase() : '';
        
        // Get role - check both role-badge and role-select elements
        let role = '';
        const roleBadgeElement = row.querySelector('td:nth-child(3) .role-badge');
        const roleSelectElement = row.querySelector('td:nth-child(3) .role-select');
        
        if (roleBadgeElement) {
            // For current user (role-badge) - remove "(You)" text
            role = roleBadgeElement.textContent.toLowerCase().replace(' (you)', '').trim();
        } else if (roleSelectElement) {
            // For other users (role-select)
            role = roleSelectElement.value.toLowerCase();
        }
        
        // Get status from the status badge
        const statusElement = row.querySelector('td:nth-child(4) .status-badge');
        const status = statusElement ? statusElement.textContent.toLowerCase() : '';
        
        const matchesSearch = username.includes(searchTerm);
        const matchesRole = !roleFilter || role.includes(roleFilter);
        
        if (matchesSearch && matchesRole) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Filter bidders functionality
function filterBidders() {
    const searchTerm = document.getElementById('bidderSearch').value.toLowerCase();
    const statusFilter = document.getElementById('bidderStatusFilter').value;
    const rows = document.querySelectorAll('.bidders-table tbody tr');
    
    rows.forEach(row => {
        const companyName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const contactPerson = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const email = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(6) .status-badge').textContent.toLowerCase();
        
        const matchesSearch = companyName.includes(searchTerm) || 
                             contactPerson.includes(searchTerm) || 
                             email.includes(searchTerm);
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        
        if (matchesSearch && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Clear all filters function
function clearAllFilters() {
    // Clear all text inputs
    const textInputs = document.querySelectorAll('.filter-form input[type="text"], .filter-form input[type="date"], .filter-form input[type="time"]');
    textInputs.forEach(input => {
        input.value = '';
    });
    
    // Reset all select dropdowns to first option (empty/default)
    const selectInputs = document.querySelectorAll('.filter-form select');
    selectInputs.forEach(select => {
        select.selectedIndex = 0;
    });
    
    // Uncheck all checkboxes
    const checkboxes = document.querySelectorAll('.filter-form input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Redirect to projects tab without any filter parameters
    window.location.href = window.location.pathname + '?tab=projects';
}

// Clear user filters function
function clearUserFilters() {
    const userSearchInput = document.getElementById('userSearch');
    const roleFilter = document.getElementById('roleFilter');
    
    if (userSearchInput) {
        userSearchInput.value = '';
    }
    
    if (roleFilter) {
        roleFilter.selectedIndex = 0;
    }
    
    // Show all users
    const rows = document.querySelectorAll('.users-table tbody tr');
    rows.forEach(row => {
        row.style.display = '';
    });
}

// Clear bidder filters function
function clearBidderFilters() {
    const bidderSearchInput = document.getElementById('bidderSearch');
    const bidderStatusFilter = document.getElementById('bidderStatusFilter');
    
    if (bidderSearchInput) {
        bidderSearchInput.value = '';
    }
    
    if (bidderStatusFilter) {
        bidderStatusFilter.selectedIndex = 0;
    }
    
    // Show all bidders
    const rows = document.querySelectorAll('.bidders-table tbody tr');
    rows.forEach(row => {
        row.style.display = '';
    });
}

// User role change functionality
function changeUserRole(userId, newRole) {
    // Show loading state
    const select = event.target;
    const originalValue = select.value;
    
    // Disable select during request
    select.disabled = true;
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'change_user_role');
    formData.append('user_id', userId);
    formData.append('new_role', newRole);
    formData.append('ajax', '1'); // Add AJAX flag
    
    // Send AJAX request
    fetch('dashboard.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(data => {
        console.log('Response:', data); // Debug log
        try {
            const result = JSON.parse(data);
            if (result.success) {
                showToast(`User role changed to ${newRole} successfully!`, 'success');
                
                // Update the select styling to show success
                select.classList.add('role-changed');
                setTimeout(() => {
                    select.classList.remove('role-changed');
                }, 2000);
                
                // Refresh the filter to show updated roles
                filterUsers();
            } else {
                showToast(result.message || result.error || 'Failed to change user role', 'error');
                // Revert to original value
                select.value = originalValue;
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Raw Response:', data);
            showToast('Error processing response', 'error');
            select.value = originalValue;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
        select.value = originalValue;
    })
    .finally(() => {
        select.disabled = false;
    });
}

// Edit project functionality
function editProject(projectId) {
    // Show loading state
    showToast('Loading project data...', 'success');
    
    // Fetch project data
    const formData = new FormData();
    formData.append('action', 'get_project');
    formData.append('project_id', projectId);
    formData.append('ajax', '1');
    
    fetch('dashboard.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(data => {
        try {
            const result = JSON.parse(data);
            if (result.success) {
                populateEditForm(result.project);
                showModal('edit-project-modal');
            } else {
                showToast(result.error || 'Error loading project data', 'error');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            showToast('Error processing response', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// Populate edit form with project data
function populateEditForm(project) {
    // Set project ID
    document.getElementById('edit_project_id').value = project.id;
    
    // Parse due date
    const dueDate = new Date(project.due_date);
    document.getElementById('edit_due_month').value = dueDate.getMonth() + 1;
    document.getElementById('edit_due_day').value = dueDate.getDate();
    document.getElementById('edit_due_year').value = dueDate.getFullYear();
    
    // Parse due time
    const dueTime = project.due_time;
    const timeMatch = dueTime.match(/(\d{1,2}):(\d{2}):(\d{2})/);
    if (timeMatch) {
        let hour = parseInt(timeMatch[1]);
        const minute = timeMatch[2];
        const ampm = hour >= 12 ? 'PM' : 'AM';
        if (hour > 12) hour -= 12;
        if (hour === 0) hour = 12;
        
        document.getElementById('edit_due_hour').value = hour;
        document.getElementById('edit_due_minute').value = minute;
        document.getElementById('edit_due_ampm').value = ampm;
    }
    
    // Set time zone
    document.getElementById('edit_time_zone').value = project.time_zone;
    
    // Parse assign date
    const assignDate = new Date(project.assign_date);
    document.getElementById('edit_assign_month').value = assignDate.getMonth() + 1;
    document.getElementById('edit_assign_day').value = assignDate.getDate();
    document.getElementById('edit_assign_year').value = assignDate.getFullYear();
    
    // Set text fields
    document.getElementById('edit_title').value = project.title;
    document.getElementById('edit_state').value = project.state;
    document.getElementById('edit_code').value = project.code;
    
    // Set checkboxes
    document.getElementById('edit_nature_fbo').checked = project.nature_fbo == 1;
    document.getElementById('edit_nature_state').checked = project.nature_state == 1;
    document.getElementById('edit_type_online').checked = project.type_online == 1;
    document.getElementById('edit_type_email').checked = project.type_email == 1;
    document.getElementById('edit_type_sealed').checked = project.type_sealed == 1;
    document.getElementById('edit_reason_rfq').checked = project.reason_rfq == 1;
    document.getElementById('edit_reason_rfi').checked = project.reason_rfi == 1;
    document.getElementById('edit_reason_rejection').checked = project.reason_rejection == 1;
    document.getElementById('edit_reason_other').checked = project.reason_other == 1;
    
    // Set status radio buttons
    if (project.status_submitted == 1) {
        document.getElementById('edit_status_submitted').checked = true;
    } else if (project.status_not_submitted == 1) {
        document.getElementById('edit_status_not_submitted').checked = true;
    } else if (project.status_no_result == 1) {
        document.getElementById('edit_status_no_result').checked = true;
    }
    
    // Set completed radio buttons
    if (project.completed == 1) {
        document.querySelector('input[name="completed"][value="1"]').checked = true;
    } else {
        document.querySelector('input[name="completed"][value="0"]').checked = true;
    }
    
    // Set quotes field (admin only)
    const quotesField = document.getElementById('edit_quotes');
    if (quotesField) {
        quotesField.value = project.quotes || '';
    }
    
    // Display existing files
    displayExistingFiles(project.files || []);
    
    // Initialize edit file upload functionality
    initializeEditFileUpload();
    
    // Update completed field background based on selected types
    setTimeout(() => {
        const updateCompletedFieldBackground = () => {
            const typeOnline = document.querySelector('input[name="type_online"]');
            const typeEmail = document.querySelector('input[name="type_email"]');
            const typeSealed = document.querySelector('input[name="type_sealed"]');
            const completedField = document.querySelector('input[name="completed"]:checked');
            
            if (!completedField) return;
            
            // Get the parent label of the checked completed radio button
            const completedLabel = completedField.closest('label');
            if (!completedLabel) return;
            
            // Remove any existing type-based background classes
            completedLabel.classList.remove('type-online-bg', 'type-email-bg', 'type-sealed-bg', 'type-multiple-bg');
            
            // Determine which type is selected and apply appropriate background
            const selectedTypes = [];
            if (typeOnline && typeOnline.checked) selectedTypes.push('online');
            if (typeEmail && typeEmail.checked) selectedTypes.push('email');
            if (typeSealed && typeSealed.checked) selectedTypes.push('sealed');
            
            if (selectedTypes.length === 1) {
                // Single type selected
                const type = selectedTypes[0];
                completedLabel.classList.add(`type-${type}-bg`);
            } else if (selectedTypes.length > 1) {
                // Multiple types selected
                completedLabel.classList.add('type-multiple-bg');
            }
        };
        
        updateCompletedFieldBackground();
    }, 100);
}

// Display existing files
function displayExistingFiles(files) {
    const container = document.getElementById('existing-files-container');
    if (files.length === 0) {
        container.innerHTML = '<p style="color: #6c757d; font-style: italic;">No files uploaded yet.</p>';
        return;
    }
    
    let html = '<div class="existing-files-list">';
    files.forEach(file => {
        const fileName = file.file_path.split('/').pop();
        html += `
            <div class="existing-file-item">
                <i class="fas fa-file"></i>
                <span class="file-name">${fileName}</span>
                <span class="file-type">(${file.file_type})</span>
                <a href="${file.file_path}" target="_blank" class="btn-secondary" style="padding: 2px 8px; font-size: 0.8rem;">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

// Initialize edit file upload functionality
function initializeEditFileUpload() {
    const addFileBtn = document.getElementById('edit-add-file-btn');
    const fileContainer = document.getElementById('edit-file-upload-container');
    
    if (addFileBtn && fileContainer) {
        // Clear existing event listeners
        addFileBtn.replaceWith(addFileBtn.cloneNode(true));
        const newAddFileBtn = document.getElementById('edit-add-file-btn');
        
        newAddFileBtn.addEventListener('click', function() {
            const newRow = document.createElement('div');
            newRow.className = 'file-upload-row';
            newRow.innerHTML = `
                <select name="file_types[]" required>
                    <option value="">Select File Type</option>
                    <option value="RFQ">RFQ</option>
                    <option value="RFI">RFI</option>
                    <option value="Rejection">Rejection</option>
                </select>
                <input type="file" name="project_files[]" required>
                <button type="button" class="remove-file">Remove</button>
            `;
            
            fileContainer.appendChild(newRow);
            
            // Add event listener to new remove button
            const removeBtn = newRow.querySelector('.remove-file');
            removeBtn.addEventListener('click', function() {
                newRow.remove();
            });
        });
        
        // Add event listeners to existing remove buttons
        document.querySelectorAll('#edit-file-upload-container .remove-file').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.file-upload-row').remove();
            });
        });
    }
}

// Handle edit project form submission
function initializeEditProjectForm() {
    const editForm = document.getElementById('edit-project-form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            // Create form data
            const formData = new FormData(this);
            formData.append('ajax', '1');
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        showToast('Project updated successfully!', 'success');
                        hideModal('edit-project-modal');
                        // Reload the page to refresh the projects list
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast(result.error || 'Error updating project', 'error');
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    showToast('Error processing response', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error occurred', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}

// Make functions globally available
window.showModal = showModal;
window.hideModal = hideModal;
window.showToast = showToast;
window.deleteProject = deleteProject;
window.clearAllFilters = clearAllFilters;
window.clearUserFilters = clearUserFilters;
window.editUser = editUser;
window.filterUsers = filterUsers;
window.filterBidders = filterBidders;
window.changeUserRole = changeUserRole;
window.editProject = editProject;
window.populateEditForm = populateEditForm;
window.displayExistingFiles = displayExistingFiles;
window.initializeEditFileUpload = initializeEditFileUpload;
window.initializeEditProjectForm = initializeEditProjectForm;
window.editBidder = editBidder;
window.deleteBidder = deleteBidder;
window.populateEditBidderForm = populateEditBidderForm;
window.initializeBidderForms = initializeBidderForms;
window.applyStatusRowColors = window.applyStatusRowColors;
window.viewQuotes = viewQuotes;
window.addQuotes = addQuotes;

// Show success message if present in URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('success') === '1') {
    const tab = urlParams.get('tab') || 'projects';
    if (tab === 'users') {
        showToast('User created successfully!', 'success');
    } else {
        showToast('Project created successfully!', 'success');
    }
    // Remove success parameter from URL
    window.history.replaceState({}, document.title, window.location.pathname + '?tab=' + tab);
}

// Bidder management functionality
function editBidder(bidderId) {
    // Show loading state
    showToast('Loading bidder data...', 'success');
    
    // Fetch bidder data
    const formData = new FormData();
    formData.append('action', 'get_bidder');
    formData.append('bidder_id', bidderId);
    formData.append('ajax', '1');
    
    fetch('dashboard.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(data => {
        try {
            const result = JSON.parse(data);
            if (result.success) {
                populateEditBidderForm(result.bidder);
                showModal('edit-bidder-modal');
            } else {
                showToast(result.error || 'Error loading bidder data', 'error');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            showToast('Error processing response', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// Populate edit bidder form with data
function populateEditBidderForm(bidder) {
    document.getElementById('edit_bidder_id').value = bidder.id;
    document.getElementById('edit_company_name').value = bidder.company_name;
    document.getElementById('edit_website').value = bidder.website || '';
    document.getElementById('edit_contact_person').value = bidder.contact_person;
    document.getElementById('edit_email').value = bidder.email;
    document.getElementById('edit_phone').value = bidder.phone;
    document.getElementById('edit_address').value = bidder.address || '';
    document.getElementById('edit_status').value = bidder.status;
    document.getElementById('edit_notes').value = bidder.notes || '';
}

// Delete bidder functionality
function deleteBidder(bidderId) {
    if (confirm('Are you sure you want to delete this bidder?')) {
        const formData = new FormData();
        formData.append('action', 'delete_bidder');
        formData.append('bidder_id', bidderId);
        formData.append('ajax', '1');
        
        fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(data => {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    showToast('Bidder deleted successfully!', 'success');
                    // Reload the page to refresh the bidders list
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(result.error || 'Error deleting bidder.', 'error');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                showToast('Error processing response', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting bidder.', 'error');
        });
    }
}

// Initialize bidder form submissions
function initializeBidderForms() {
    // Add bidder form submission
    const addBidderForm = document.getElementById('add-bidder-form');
    if (addBidderForm) {
        addBidderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;
            
            // Create form data
            const formData = new FormData(this);
            formData.append('ajax', '1');
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        showToast('Bidder added successfully!', 'success');
                        hideModal('add-bidder-modal');
                        // Reset form
                        this.reset();
                        // Reload the page to refresh the bidders list
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast(result.error || 'Error adding bidder', 'error');
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    showToast('Error processing response', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error occurred', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Edit bidder form submission
    const editBidderForm = document.getElementById('edit-bidder-form');
    if (editBidderForm) {
        editBidderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            // Create form data
            const formData = new FormData(this);
            formData.append('ajax', '1');
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        showToast('Bidder updated successfully!', 'success');
                        hideModal('edit-bidder-modal');
                        // Reload the page to refresh the bidders list
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast(result.error || 'Error updating bidder', 'error');
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    showToast('Error processing response', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error occurred', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}

// Type-based completed field background color
function initializeTypeBasedCompletedField() {
    // Function to update completed field background based on selected types
    function updateCompletedFieldBackground() {
        // Handle both add and edit forms
        const typeOnline = document.querySelector('input[name="type_online"]');
        const typeEmail = document.querySelector('input[name="type_email"]');
        const typeSealed = document.querySelector('input[name="type_sealed"]');
        const completedField = document.querySelector('input[name="completed"]:checked');
        
        if (!completedField) return;
        
        // Get the parent label of the checked completed radio button
        const completedLabel = completedField.closest('label');
        if (!completedLabel) return;
        
        // Remove any existing type-based background classes
        completedLabel.classList.remove('type-online-bg', 'type-email-bg', 'type-sealed-bg', 'type-multiple-bg');
        
        // Determine which type is selected and apply appropriate background
        const selectedTypes = [];
        if (typeOnline && typeOnline.checked) selectedTypes.push('online');
        if (typeEmail && typeEmail.checked) selectedTypes.push('email');
        if (typeSealed && typeSealed.checked) selectedTypes.push('sealed');
        
        if (selectedTypes.length === 1) {
            // Single type selected
            const type = selectedTypes[0];
            completedLabel.classList.add(`type-${type}-bg`);
        } else if (selectedTypes.length > 1) {
            // Multiple types selected
            completedLabel.classList.add('type-multiple-bg');
        }
    }
    
    // Add event listeners to type checkboxes (both add and edit forms)
    const typeCheckboxes = document.querySelectorAll('input[name="type_online"], input[name="type_email"], input[name="type_sealed"]');
    typeCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateCompletedFieldBackground);
    });
    
    // Add event listeners to completed radio buttons (both add and edit forms)
    const completedRadios = document.querySelectorAll('input[name="completed"]');
    completedRadios.forEach(radio => {
        radio.addEventListener('change', updateCompletedFieldBackground);
    });
    
    // Initial call to set background on page load
    updateCompletedFieldBackground();
}

// Status-based table row background colors
function initializeStatusBasedRowColors() {
    // Function to apply status-based background colors to table rows
    function applyStatusRowColors() {
        const projectRows = document.querySelectorAll('.projects-table tbody tr');
        
        projectRows.forEach(row => {
            // Remove any existing status classes
            row.classList.remove('status-submitted', 'status-not-submitted', 'status-no-result');
            
            // Find the status element in this row
            const statusElement = row.querySelector('.status');
            if (!statusElement) return;
            
            // Determine status and apply appropriate class
            if (statusElement.classList.contains('submitted')) {
                row.classList.add('status-submitted');
            } else if (statusElement.classList.contains('not-submitted')) {
                row.classList.add('status-not-submitted');
            } else if (statusElement.classList.contains('no-result')) {
                row.classList.add('status-no-result');
            }
        });
    }
    
    // Apply colors on page load
    applyStatusRowColors();
    
    // Apply colors after any AJAX updates (if needed)
    // This can be called after form submissions or other dynamic updates
    window.applyStatusRowColors = applyStatusRowColors;
}

// View quotes function
function viewQuotes(projectId) {
    // Fetch project data to get quotes
    const formData = new FormData();
    formData.append('action', 'get_project');
    formData.append('project_id', projectId);
    formData.append('ajax', '1');
    
    fetch('dashboard.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(data => {
        try {
            const result = JSON.parse(data);
            if (result.success && result.project.quotes) {
                // Show quotes in a modal or alert
                alert('Project Quotes:\n\n' + result.project.quotes);
            } else {
                showToast('No quotes found for this project', 'info');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            showToast('Error loading quotes', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// Add quotes function
function addQuotes(projectId) {
    const quotes = prompt('Enter quotes for this project:');
    if (quotes !== null) {
        // Update project with new quotes
        const formData = new FormData();
        formData.append('action', 'update_quotes');
        formData.append('project_id', projectId);
        formData.append('quotes', quotes);
        formData.append('ajax', '1');
        
        fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(data => {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    showToast('Quotes added successfully!', 'success');
                    // Reload the page to refresh the table
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(result.message || 'Error adding quotes', 'error');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                showToast('Error processing response', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error occurred', 'error');
        });
    }
}

function initializeReasonRowColor() {
    // Find the Reason for No Bidding form-section
    const reasonSection = Array.from(document.querySelectorAll('.form-section')).find(section => {
        const h3 = section.querySelector('h3');
        return h3 && h3.textContent.toLowerCase().includes('reason for no bidding');
    });
    if (!reasonSection) return;
    
    const rfq = reasonSection.querySelector('input[name="reason_rfq"]');
    const rfi = reasonSection.querySelector('input[name="reason_rfi"]');
    const rejection = reasonSection.querySelector('input[name="reason_rejection"]');
    const other = reasonSection.querySelector('input[name="reason_other"]');
    
    if (!rfq || !rfi) return;
    
    function updateReasonRowColor() {
        reasonSection.classList.remove('reason-row-rfi', 'reason-row-rfq');
        
        // Priority: RFQ (red) takes precedence over RFI (blue)
        if (rfq.checked) {
            reasonSection.classList.add('reason-row-rfq');
        } else if (rfi.checked) {
            reasonSection.classList.add('reason-row-rfi');
        }
        // Rejection and Other don't change the row color
    }
    
    // Add event listeners to all reason checkboxes
    [rfq, rfi, rejection, other].forEach(checkbox => {
        if (checkbox) {
            checkbox.addEventListener('change', updateReasonRowColor);
        }
    });
    
    // Initial call
    updateReasonRowColor();
} 