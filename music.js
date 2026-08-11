/* Extracted from music.php */

// Hamburger menu toggle
        document.querySelector('.hamburger').addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Audio player
        const audio = document.getElementById('audio-player');
        const currentTrack = document.getElementById('current-track');

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('play-btn') || e.target.parentElement.classList.contains('play-btn')) {
                const btn = e.target.closest('.play-btn');
                const file = btn.dataset.file;
                audio.src = `music/${encodeURIComponent(file)}`;
                audio.play();
                currentTrack.textContent = btn.parentElement.querySelector('p').textContent;
            }
        });

        // Handle music upload with AJAX and progress bar
        const uploadForm = document.getElementById('music-upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(uploadForm);
                const submitBtn = document.getElementById('music-submit');
                const messageDiv = document.getElementById('upload-message');
                const progressContainer = document.getElementById('progress-container');
                const progressFill = document.getElementById('progress-fill');
                const uploadPercent = document.getElementById('upload-percent');

                messageDiv.innerHTML = '';

                const fileInput = document.getElementById('music');
                const file = fileInput.files[0];
                if (!file) {
                    messageDiv.innerHTML = '<div class="error">No file selected. Please choose an MP3 file.</div>';
                    return;
                }

                if (file.type !== 'audio/mpeg' && !file.name.toLowerCase().endsWith('.mp3')) {
                    messageDiv.innerHTML = '<div class="error">Invalid file type. Only MP3 files are allowed.</div>';
                    return;
                }

                const maxSize = <?php echo $settings['music_size_limit_mb'] * 1024 * 1024; ?>;
                if (file.size > maxSize) {
                    messageDiv.innerHTML = '<div class="error">File size exceeds <?php echo $settings['music_size_limit_mb']; ?>MB limit.</div>';
                    return;
                }

                console.log('Uploading file details:', {
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    formDataEntries: Array.from(formData.entries())
                });

                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
                progressContainer.style.display = 'block';
                uploadPercent.textContent = '0%';
                progressFill.style.width = '0%';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload.php', true);

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const percent = Math.round((event.loaded / event.total) * 100);
                        uploadPercent.textContent = percent + '%';
                        progressFill.style.width = percent + '%';
                    }
                };

                xhr.onload = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Music';
                    progressContainer.style.display = 'none';

                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            console.log('Server response:', data);
                            const message = document.createElement('div');
                            message.className = data.success ? 'success' : 'error';
                            message.textContent = data.success ? data.message : (data.error || 'Unknown error');
                            messageDiv.appendChild(message);

                            if (data.success) {
                                // Reload page to update library
                                location.reload();
                            }
                        } catch (e) {
                            console.error('JSON parse error:', e, 'Raw response:', xhr.responseText);
                            const message = document.createElement('div');
                            message.className = 'error';
                            message.textContent = 'Invalid server response: ' + e.message + ' (Response starts with: ' + xhr.responseText.substring(0, 20) + '...)';
                            messageDiv.appendChild(message);
                        }
                    } else {
                        console.error('Upload failed with status:', xhr.status, 'Response:', xhr.responseText);
                        const message = document.createElement('div');
                        message.className = 'error';
                        message.textContent = 'Upload failed with status: ' + xhr.status + ' (Response: ' + xhr.responseText.substring(0, 20) + '...)';
                        messageDiv.appendChild(message);
                    }
                };

                xhr.onerror = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Music';
                    progressContainer.style.display = 'none';
                    console.error('Network error during upload');
                    const message = document.createElement('div');
                    message.className = 'error';
                    message.textContent = 'Network error during upload';
                    messageDiv.appendChild(message);
                };

                xhr.ontimeout = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Music';
                    progressContainer.style.display = 'none';
                    console.error('Upload timed out');
                    const message = document.createElement('div');
                    message.className = 'error';
                    message.textContent = 'Upload timed out';
                    messageDiv.appendChild(message);
                };

                xhr.timeout = 30000; // 30 seconds timeout
                xhr.send(formData);
            });
        }

        // Handle cover upload with AJAX and progress bar
        const coverForm = document.getElementById('cover-upload-form');
        if (coverForm) {
            coverForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(coverForm);
                formData.append('action', 'upload_cover');
                const submitBtn = document.getElementById('cover-submit');
                const messageDiv = document.getElementById('cover-message');
                const progressContainer = document.getElementById('cover-progress-container');
                const progressFill = document.getElementById('cover-progress-fill');
                const uploadPercent = document.getElementById('cover-percent');

                messageDiv.innerHTML = '';

                const fileInput = document.getElementById('cover');
                const file = fileInput.files[0];
                if (!file) {
                    messageDiv.innerHTML = '<div class="error">No file selected. Please choose a cover image.</div>';
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    messageDiv.innerHTML = '<div class="error">Invalid file type. Only JPEG, PNG, and WebP are allowed.</div>';
                    return;
                }

                const maxSize = <?php echo $settings['thumbnail_size_limit_mb'] * 1024 * 1024; ?>;
                if (file.size > maxSize) {
                    messageDiv.innerHTML = '<div class="error">File size exceeds <?php echo $settings['thumbnail_size_limit_mb']; ?>MB limit.</div>';
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
                progressContainer.style.display = 'block';
                uploadPercent.textContent = '0%';
                progressFill.style.width = '0%';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload_cover.php', true);

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const percent = Math.round((event.loaded / event.total) * 100);
                        uploadPercent.textContent = percent + '%';
                        progressFill.style.width = percent + '%';
                    }
                };

                xhr.onload = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Cover';
                    progressContainer.style.display = 'none';

                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            const message = document.createElement('div');
                            message.className = data.success ? 'success' : 'error';
                            message.textContent = data.success ? data.message : data.error;
                            messageDiv.appendChild(message);

                            if (data.success) {
                                // Optionally refresh the page or update the UI
                                location.reload();
                            }
                        } catch (e) {
                            console.error('JSON parse error:', e, 'Raw response:', xhr.responseText);
                            const message = document.createElement('div');
                            message.className = 'error';
                            message.textContent = 'Invalid server response: ' + e.message;
                            messageDiv.appendChild(message);
                        }
                    } else {
                        console.error('Upload failed with status:', xhr.status, 'Response:', xhr.responseText);
                        const message = document.createElement('div');
                        message.className = 'error';
                        message.textContent = 'Upload failed with status: ' + xhr.status;
                        messageDiv.appendChild(message);
                    }
                };

                xhr.onerror = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Cover';
                    progressContainer.style.display = 'none';
                    console.error('Network error during upload');
                    const message = document.createElement('div');
                    message.className = 'error';
                    message.textContent = 'Network error during upload';
                    messageDiv.appendChild(message);
                };

                xhr.ontimeout = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Cover';
                    progressContainer.style.display = 'none';
                    console.error('Upload timed out');
                    const message = document.createElement('div');
                    message.className = 'error';
                    message.textContent = 'Upload timed out';
                    messageDiv.appendChild(message);
                };

                xhr.timeout = 30000; // 30 seconds timeout
                xhr.send(formData);
            });
        }

        // Handle music deletion with AJAX
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const music = button.dataset.music;

                    if (confirm(`Are you sure you want to delete ${music}?`)) {
                        const formData = new FormData();
                        formData.append('action', 'delete_music');
                        formData.append('music', music);

                        fetch('music.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                button.parentElement.remove();
                                const messageDiv = document.getElementById('upload-message');
                                const message = document.createElement('div');
                                message.className = 'success';
                                message.textContent = data.message;
                                messageDiv.appendChild(message);
                            } else {
                                const messageDiv = document.getElementById('upload-message');
                                const message = document.createElement('div');
                                message.className = 'error';
                                message.textContent = data.error;
                                messageDiv.appendChild(message);
                            }
                        })
                        .catch(error => {
                            const messageDiv = document.getElementById('upload-message');
                            const message = document.createElement('div');
                            message.className = 'error';
                            message.textContent = 'Delete failed: ' + error.message;
                            messageDiv.appendChild(message);
                        });
                    }
                });
            });
        });