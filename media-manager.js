/* Extracted from media_manager.php */

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

        // Delete item
        function deleteItem(type, file) {
            if (!confirm(`Delete ${file} permanently?`)) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&type=${type}&file=${encodeURIComponent(file)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(`${file} deleted successfully`);
                    location.reload();
                } else {
                    alert(data.error || 'Delete failed - check permissions');
                }
            })
            .catch(() => alert('Network error'));
        }

        // Rename
        function promptRename(type, file) {
            const newName = prompt(`Rename ${file} to:`, file);
            if (!newName || newName === file) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=rename&type=${type}&file=${encodeURIComponent(file)}&new_name=${encodeURIComponent(newName)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(`Renamed to ${newName}`);
                    location.reload();
                } else {
                    alert(data.error || 'Rename failed');
                }
            });
        }

        // Thumbnail upload
        document.querySelectorAll('input[type="file"][name="thumbnail"]').forEach(input => {
            input.addEventListener('change', function() {
                if (!this.files.length) return;

                const form = this.closest('form');
                const fileName = form.querySelector('input[name="file"]').value;
                if (!confirm(`Upload thumbnail for ${fileName}?`)) {
                    this.value = '';
                    return;
                }

                const formData = new FormData(form);
                const progressBar = form.querySelector('.progress-bar');
                const statusDiv = form.querySelector('.status-message');
                const progressContainer = form.querySelector('.progress-container');

                progressContainer.style.display = 'block';
                progressBar.style.width = '0%';
                statusDiv.style.display = 'none';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);

                xhr.upload.onprogress = ev => {
                    if (ev.lengthComputable) {
                        const percent = (ev.loaded / ev.total) * 100;
                        progressBar.style.width = percent + '%';
                    }
                };

                xhr.onload = () => {
                    progressContainer.style.display = 'none';
                    let data;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch {
                        statusDiv.className = 'status-message status-error';
                        statusDiv.textContent = 'Server error';
                        statusDiv.style.display = 'block';
                        return;
                    }

                    if (data.success) {
                        statusDiv.className = 'status-message status-success';
                        statusDiv.textContent = 'Thumbnail uploaded!';
                        statusDiv.style.display = 'block';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        statusDiv.className = 'status-message status-error';
                        statusDiv.textContent = data.error || 'Upload failed';
                        statusDiv.style.display = 'block';
                    }
                };

                xhr.onerror = () => {
                    progressContainer.style.display = 'none';
                    statusDiv.className = 'status-message status-error';
                    statusDiv.textContent = 'Network error';
                    statusDiv.style.display = 'block';
                };

                xhr.send(formData);
            });
        });

        // Music search
        function filterMusic() {
            const query = document.getElementById('musicSearch').value.toLowerCase();
            document.querySelectorAll('#musicItems .media-card').forEach(card => {
                const name = card.querySelector('.media-name').textContent.toLowerCase();
                card.style.display = name.includes(query) ? '' : 'none';
            });
        }