document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('cp_tracking_active');
    const details = document.getElementById('cp-tracking-details');
    if (checkbox && details) {
        checkbox.addEventListener('change', function() {
            details.classList.toggle('hidden-tracking', !this.checked);
        });
    }
});