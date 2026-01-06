// Profile Modal JavaScript

// Show modal notification function
function showMessageModal(message, type) {
    // Remove existing modal if any
    const existingModal = document.querySelector('.message-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.className = `message-modal message-modal-${type}`;
    
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const title = type === 'success' ? 'Success!' : 'Error!';
    
    modal.innerHTML = `
        <div class="message-modal-content">
            <div class="message-modal-header">
                <button class="message-modal-close" onclick="closeMessageModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon">
                    <i class="fas ${iconClass}"></i>
                </div>
                <h3 class="message-modal-title">${title}</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message">${message}</p>
                <div class="message-modal-actions">
                    <button class="message-modal-btn message-modal-btn-primary" onclick="closeMessageModal()">
                        OK
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Show modal with animation
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    // Auto close after 3 seconds
    setTimeout(() => {
        closeMessageModal();
    }, 3000);
}

function closeMessageModal() {
    const modal = document.querySelector('.message-modal');
    if (modal) {
        modal.classList.add('hiding');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Initialize profile modal forms
function initializeProfileForms() {
    console.log('Initializing profile forms...');
    
    // Handle profile update form
    const updateForm = document.getElementById('updateProfileForm');
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            console.log('Profile form submitted');
            
            const formData = new FormData(this);
            formData.append('update_profile', '1');
            
            fetch('profile_modal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        showMessageModal(data.message, 'success');
                        // Update the navbar name if it changed
                        const navbarName = document.querySelector('#navbarDropdown');
                        if (navbarName) {
                            const newName = document.getElementById('modal_fullname').value;
                            navbarName.innerHTML = '<i class="fas fa-user-circle"></i> ' + newName;
                        }
                    } else {
                        showMessageModal(data.message, 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', text);
                    showMessageModal('Server error. Please check console.', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showMessageModal('Network error. Please try again.', 'error');
            });
        });
        console.log('Profile form listener attached');
    } else {
        console.error('updateProfileForm not found');
    }
    
    // Handle password change form
    const passwordForm = document.getElementById('changePasswordForm');
    if (passwordForm) {
        // Check if already initialized
        if (passwordForm.dataset.initialized === 'true') {
            console.log('Password form already initialized, skipping');
            return;
        }
        passwordForm.dataset.initialized = 'true';
        
        let isSubmitting = false; // Prevent duplicate submissions
        
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Prevent duplicate submissions
            if (isSubmitting) {
                console.log('Form already submitting, ignoring duplicate request');
                return false;
            }
            
            isSubmitting = true;
            console.log('Password form submitted');
            
            const formData = new FormData(this);
            formData.append('change_password', '1');
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
            }
            
            fetch('profile_modal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        showMessageModal(data.message, 'success');
                        // Clear the form
                        passwordForm.reset();
                    } else {
                        showMessageModal(data.message, 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', text);
                    showMessageModal('Server error. Please check console.', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showMessageModal('Network error. Please try again.', 'error');
            })
            .finally(() => {
                // Re-enable submit button
                isSubmitting = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-key"></i> Change Password';
                }
            });
            
            return false;
        })
        console.log('Password form listener attached');
    } else {
        console.error('changePasswordForm not found');
    }
}

// Initialize password toggle icons
function initializePasswordToggles() {
    console.log('Initializing password toggles...');
    
    // Toggle current password visibility
    const toggleCurrentPassword = document.getElementById('toggleCurrentPassword');
    const currentPasswordInput = document.getElementById('modal_current_password');
    
    if (toggleCurrentPassword && currentPasswordInput) {
        toggleCurrentPassword.addEventListener('click', function() {
            const type = currentPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            currentPasswordInput.setAttribute('type', type);
            
            if (type === 'password') {
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            } else {
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            }
        });
        console.log('Current password toggle attached');
    }

    // Toggle new password visibility
    const toggleNewPassword = document.getElementById('toggleNewPassword');
    const newPasswordInput = document.getElementById('modal_new_password');
    
    if (toggleNewPassword && newPasswordInput) {
        toggleNewPassword.addEventListener('click', function() {
            const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            newPasswordInput.setAttribute('type', type);
            
            if (type === 'password') {
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            } else {
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            }
        });
        console.log('New password toggle attached');
    }

    // Toggle confirm password visibility
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPasswordInput = document.getElementById('modal_confirm_password');
    
    if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            
            if (type === 'password') {
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            } else {
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            }
        });
        console.log('Confirm password toggle attached');
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initializeProfileForms();
        initializePasswordToggles();
    });
} else {
    initializeProfileForms();
    initializePasswordToggles();
}
