/* Extracted from videos.php */

// Hamburger menu toggle (top navbar)
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        if (hamburgerBtn && mobileMenu) {
            hamburgerBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('open');
            });
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburgerBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.remove('open');
            }
        });

        // Sidebar hamburger toggle (your existing sidebar)
        const sidebar = document.querySelector('.sidebar');
        const sidebarHamburger = document.querySelector('.hamburger');
        if (sidebarHamburger && sidebar) {
            sidebarHamburger.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }

        // Handle video upload with AJAX
        const uploadForm = document.getElementById('video-upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = document.getElementById('video-submit');

                // Clear previous messages
                const existingMessages = document.querySelectorAll('.main-content .error, .main-content .success');
                existingMessages.forEach(msg => msg.remove());

                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';

                fetch('upload.php', {
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
                    console.log('Upload response:', data);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Video';

                    const messageDiv = document.createElement('div');
                    messageDiv.className = data.success ? 'success' : 'error';
                    messageDiv.textContent = data.success ? data.message : data.error;
                    document.querySelector('.main-content').prepend(messageDiv);

                    if (data.success) {
                        // Add new video to the list
                        const videoList = document.querySelector('.video-list');
                        const videoItem = document.createElement('div');
                        videoItem.className = 'video-item';
                        if (data.thumbnail) {
                            const img = document.createElement('img');
                            img.src = `videos/${data.thumbnail}`;
                            img.alt = 'Thumbnail';
                            videoItem.appendChild(img);
                        }
                        const p = document.createElement('p');
                        p.textContent = data.video;
                        videoItem.appendChild(p);
                        const deleteBtn = document.createElement('button');
                        deleteBtn.className = 'delete-btn';
                        deleteBtn.dataset.video = data.video;
                        deleteBtn.dataset.thumbnail = data.thumbnail || '';
                        deleteBtn.textContent = 'Delete';
                        videoItem.appendChild(deleteBtn);
                        videoList.prepend(videoItem);

                        // Reset form
                        uploadForm.reset();
                    }
                })
                .catch(error => {
                    console.error('Upload failed:', error);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Video';
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error';
                    errorDiv.textContent = 'Upload failed: ' + error.message;
                    document.querySelector('.main-content').prepend(errorDiv);
                });
            });
        }

        // Handle video deletion with AJAX
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const video = this.dataset.video;
                const thumbnail = this.dataset.thumbnail;

                if (confirm(`Are you sure you want to delete ${video}?`)) {
                    const formData = new FormData();
                    formData.append('action', 'delete_video');
                    formData.append('video', video);
                    formData.append('thumbnail', thumbnail);

                    fetch('videos.php', {
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
                        console.log('Delete response:', data);
                        if (data.success) {
                            this.parentElement.remove();
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'success';
                            messageDiv.textContent = data.message;
                            document.querySelector('.main-content').prepend(messageDiv);
                        } else {
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'error';
                            errorDiv.textContent = data.error;
                            document.querySelector('.main-content').prepend(errorDiv);
                        }
                    })
                    .catch(error => {
                        console.error('Delete failed:', error);
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error';
                        errorDiv.textContent = 'Delete failed: ' + error.message;
                        document.querySelector('.main-content').prepend(errorDiv);
                    });
                }
            });
        });