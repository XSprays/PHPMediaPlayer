/* Extracted from watch.php */

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

        // Cookie helpers
        function setCookie(name, value, days = 365) {
            let expires = "";
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i].trim();
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length);
            }
            return null;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const videoEl = document.querySelector('.main-video video');
            const playBtn = document.querySelector('.play-btn');
            const pauseBtn = document.querySelector('.pause-btn');
            const thumbnail = document.querySelector('.main-video img');
            const searchInput = document.querySelector('.search-bar');
            const videoList = document.querySelector('.video-list');
            const volumeSlider = document.querySelector('.volume-slider');
            const fullscreenBtn = document.querySelector('.fullscreen-btn');
            const theatreBtn = document.querySelector('.theatre-btn');
            const progressBar = document.querySelector('.progress-bar');
            const progress = document.querySelector('.progress');
            const timeDisplay = document.querySelector('.time-display');
            const videoWrapper = document.querySelector('.video-wrapper');

            if (videoEl) {
                // Volume from cookie
                const savedVolume = getCookie('mediaVolume') || 0.8;
                videoEl.volume = parseFloat(savedVolume);
                volumeSlider.value = videoEl.volume;

                volumeSlider.addEventListener('input', () => {
                    videoEl.volume = volumeSlider.value;
                    setCookie('mediaVolume', videoEl.volume.toFixed(3));
                });

                // Play / pause
                videoEl.addEventListener('play', () => {
                    if (thumbnail) thumbnail.style.display = 'none';
                    playBtn.style.display = 'none';
                    pauseBtn.style.display = 'inline-block';
                });

                playBtn.addEventListener('click', () => videoEl.play());
                pauseBtn.addEventListener('click', () => {
                    videoEl.pause();
                    pauseBtn.style.display = 'none';
                    playBtn.style.display = 'inline-block';
                });

                // Progress & time
                function formatTime(seconds) {
                    if (isNaN(seconds) || seconds < 0) return "0:00";
                    const mins = Math.floor(seconds / 60);
                    const secs = Math.floor(seconds % 60);
                    return mins + ":" + (secs < 10 ? "0" : "") + secs;
                }

                videoEl.addEventListener('timeupdate', () => {
                    if (!videoEl.duration) return;
                    const percent = (videoEl.currentTime / videoEl.duration) * 100;
                    progress.style.width = percent + '%';
                    timeDisplay.textContent = formatTime(videoEl.currentTime) + " / " + formatTime(videoEl.duration);
                });

                videoEl.addEventListener('loadedmetadata', () => {
                    timeDisplay.textContent = "0:00 / " + formatTime(videoEl.duration);
                });

                progressBar.addEventListener('click', (e) => {
                    const rect = progressBar.getBoundingClientRect();
                    const pos = (e.clientX - rect.left) / rect.width;
                    videoEl.currentTime = pos * videoEl.duration;
                });

                // Theatre mode (desktop only)
                if (theatreBtn) {
                    theatreBtn.addEventListener('click', () => {
                        if (!videoWrapper.classList.contains('theatre-mode')) {
                            videoWrapper.classList.add('theatre-mode');
                            theatreBtn.textContent = '■';
                        } else {
                            videoWrapper.classList.remove('theatre-mode');
                            theatreBtn.textContent = '◻';
                        }
                    });
                }

                // Fullscreen - use native browser fullscreen on the video element
                fullscreenBtn.addEventListener('click', () => {
                    if (!document.fullscreenElement) {
                        videoEl.requestFullscreen().catch(err => console.error('Fullscreen failed:', err));
                    } else {
                        document.exitFullscreen();
                    }
                });
            }

            // Search
            if (searchInput && videoList) {
                searchInput.addEventListener('input', () => {
                    const query = searchInput.value.toLowerCase();
                    videoList.querySelectorAll('li a').forEach(a => {
                        const name = a.querySelector('.video-name')?.textContent.toLowerCase() || '';
                        a.parentElement.style.display = name.includes(query) ? '' : 'none';
                    });
                });
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

            handleUpload('videoForm', 'videoProgress', 'video-message', 'Video uploaded successfully!');
            handleUpload('thumbForm', 'thumbProgress', 'thumb-message', 'Thumbnail uploaded successfully!');

            // Reliable modal open
            const openLink = document.getElementById('openVideoUpload');
            if (openLink) {
                openLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const modal = document.getElementById('uploadModal');
                    if (modal) modal.style.display = 'flex';
                });
            }
        });