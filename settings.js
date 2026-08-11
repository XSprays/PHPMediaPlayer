/* Extracted from settings.php */

// Hamburger menu toggle
        const sidebar = document.querySelector('.sidebar');
        const hamburger = document.querySelector('.hamburger');
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        // Handle settings form submission with AJAX
        const settingsForm = document.getElementById('settings-form');
        if (settingsForm) {
            settingsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = document.getElementById('settings-submit');

                // Clear previous messages
                const existingMessages = document.querySelectorAll('.main-content .error, .main-content .success');
                existingMessages.forEach(msg => msg.remove());

                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';

                fetch('settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Settings response:', data);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Settings';

                    const messageDiv = document.createElement('div');
                    messageDiv.className = data.success ? 'success' : 'error';
                    messageDiv.textContent = data.success ? data.message : data.error;
                    document.querySelector('.main-content').prepend(messageDiv);
                })
                .catch(error => {
                    console.error('Settings save failed:', error);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Settings';
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error';
                    errorDiv.textContent = 'Settings save failed: ' + error.message;
                    document.querySelector('.main-content').prepend(errorDiv);
                });
            });
        }