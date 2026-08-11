/* Extracted from music_player.php */

alert("This website is for only 18+.");

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

        // Cookie helpers
        function setCookie(name, value, days = 60) {
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
            const audio = document.querySelector('.main-audio');
            if (!audio) return;

            const playBtn      = document.querySelector('.play-btn');
            const pauseBtn     = document.querySelector('.pause-btn');
            const progressBar  = document.querySelector('.progress-bar');
            const progress     = document.querySelector('.progress');
            const timeDisplay  = document.querySelector('.time-display');
            const volumeSlider = document.querySelector('.volume-slider');
            const searchInput  = document.querySelector('.search-bar');
            const trackList    = document.querySelector('.track-list');

            // Volume persistence
            const savedVolume = getCookie('musicVolume');
            if (savedVolume !== null) {
                audio.volume = parseFloat(savedVolume);
                volumeSlider.value = audio.volume;
            } else {
                audio.volume = 0.8;
                volumeSlider.value = 0.8;
            }

            volumeSlider.addEventListener('input', () => {
                audio.volume = volumeSlider.value;
                setCookie('musicVolume', audio.volume.toFixed(3));
            });

            // Play / Pause
            playBtn.addEventListener('click', () => {
                audio.play().catch(() => {});
                playBtn.style.display = 'none';
                pauseBtn.style.display = 'inline-block';
            });
            pauseBtn.addEventListener('click', () => {
                audio.pause();
                pauseBtn.style.display = 'none';
                playBtn.style.display = 'inline-block';
            });

            // Progress + Time
            function formatTime(seconds) {
                if (isNaN(seconds) || seconds < 0) return "0:00";
                const mins = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return mins + ":" + (secs < 10 ? "0" : "") + secs;
            }

            audio.addEventListener('timeupdate', () => {
                if (!audio.duration || isNaN(audio.duration)) return;
                const percent = (audio.currentTime / audio.duration) * 100;
                progress.style.width = percent + '%';
                timeDisplay.textContent = formatTime(audio.currentTime) + " / " + formatTime(audio.duration);
            });

            audio.addEventListener('loadedmetadata', () => {
                timeDisplay.textContent = "0:00 / " + formatTime(audio.duration);
            });

            // Seek
            progressBar.addEventListener('click', (e) => {
                const rect = progressBar.getBoundingClientRect();
                const pos = (e.clientX - rect.left) / rect.width;
                audio.currentTime = pos * audio.duration;
            });

            // Search
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.toLowerCase();
                trackList.querySelectorAll('li').forEach(item => {
                    const name = item.querySelector('.track-name')?.textContent.toLowerCase() || '';
                    const meta = item.querySelector('.track-meta')?.textContent.toLowerCase() || '';
                    item.style.display = (name.includes(query) || meta.includes(query)) ? '' : 'none';
                });
            });

            // Initial state
            pauseBtn.style.display = 'none';
            if (audio.autoplay) {
                playBtn.style.display = 'none';
                pauseBtn.style.display = 'inline-block';
            }
        });

        // Modal controls
        function openModal() {
            document.getElementById('uploadModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('uploadModal').style.display = 'none';
            document.getElementById('mp3-message').innerHTML = '';
            document.getElementById('cover-message').innerHTML = '';
            document.getElementById('mp3Progress').style.width = '0%';
            document.getElementById('coverProgress').style.width = '0%';
        }

        window.onclick = function(event) {
            if (event.target.id === 'uploadModal') {
                closeModal();
            }
        };

        // Upload handler
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
                        console.log('Upload response:', data);
                    } catch (err) {
                        msgDiv.className = 'status-message error';
                        msgDiv.innerHTML = 'Invalid server response';
                        console.error('Raw response was:', xhr.responseText);
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

        document.addEventListener('DOMContentLoaded', () => {
            handleUpload('mp3Form',   'mp3Progress',   'mp3-message',   'MP3 uploaded successfully!');
            handleUpload('coverForm', 'coverProgress', 'cover-message', 'Cover art uploaded successfully!');
        });