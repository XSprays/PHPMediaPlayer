/* Extracted from admin.php */

// Hamburger menu toggle
        const sidebar = document.querySelector('.sidebar');
        const hamburger = document.querySelector('.hamburger');
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });