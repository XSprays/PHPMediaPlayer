/* Extracted from gallery.php */

// Hamburger menu toggle
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

        // Zoom only (centered, no panning/dragging)
        const previewImg = document.getElementById('previewImg');
        if (previewImg) {
            let scale = 1;

            // Mouse wheel zoom (desktop)
            previewImg.addEventListener('wheel', (e) => {
                e.preventDefault();
                const delta = e.deltaY < 0 ? 0.1 : -0.1;
                scale = Math.max(0.5, Math.min(5, scale + delta));
                previewImg.style.transform = `scale(${scale})`;
                previewImg.style.transformOrigin = 'center center';
            });

            // Pinch zoom (mobile/touch)
            let startDistance = 0;
            previewImg.addEventListener('touchstart', (e) => {
                if (e.touches.length === 2) {
                    e.preventDefault();
                    const touch1 = e.touches[0];
                    const touch2 = e.touches[1];
                    startDistance = Math.hypot(
                        touch2.clientX - touch1.clientX,
                        touch2.clientY - touch1.clientY
                    );
                }
            }, { passive: false });

            previewImg.addEventListener('touchmove', (e) => {
                if (e.touches.length === 2) {
                    e.preventDefault();
                    const touch1 = e.touches[0];
                    const touch2 = e.touches[1];
                    const currentDistance = Math.hypot(
                        touch2.clientX - touch1.clientX,
                        touch2.clientY - touch1.clientY
                    );

                    const delta = currentDistance / startDistance;
                    scale = Math.max(0.5, Math.min(5, scale * delta));
                    previewImg.style.transform = `scale(${scale})`;
                    previewImg.style.transformOrigin = 'center center';

                    startDistance = currentDistance;
                }
            }, { passive: false });
        }

        // Upload handlers
        function handleUpload(formId, progressId, messageId, successText) {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);

                const msgDiv = document.getElementById(messageId);
                const progressBar = document.getElementById(progressId);

                msgDiv.innerHTML = 'Uploading...';
                msgDiv.className = '';
                progressBar.style.width = '0%';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload.php', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 100;
                        progressBar.style.width = percent + '%';
                    }
                };

                xhr.onload = function() {
                    progressBar.style.width = '100%';
                    let data;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch (err) {
                        msgDiv.className = 'status-message error';
                        msgDiv.innerHTML = 'Invalid server response';
                        console.error('Raw response:', xhr.responseText);
                        return;
                    }

                    if (data.success) {
                        msgDiv.className = 'status-message success';
                        msgDiv.innerHTML = successText;
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        msgDiv.className = 'status-message error';
                        msgDiv.innerHTML = data.error || 'Upload failed';
                    }
                };

                xhr.onerror = function() {
                    progressBar.style.width = '0%';
                    msgDiv.className = 'status-message error';
                    msgDiv.innerHTML = 'Network error';
                };

                xhr.send(formData);
            });
        }

        handleUpload('imageForm', 'imageProgress', 'image-message', 'Image uploaded successfully!');
        handleUpload('thumbForm', 'thumbProgress', 'thumb-message', 'Thumbnail uploaded successfully!');

        // Open modal
        const openLink = document.getElementById('openGalleryUpload');
        if (openLink) {
            openLink.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const modal = document.getElementById('uploadModal');
                if (modal) modal.style.display = 'flex';
            });
        }