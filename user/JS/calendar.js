// user/js/calendar.js

// Filter reservations by type
function filterReservations(filterType) {
    // Update active button state
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Find the clicked button and add active class
    const clickedButton = Array.from(buttons).find(btn => {
        const onclickAttr = btn.getAttribute('onclick');
        return onclickAttr && onclickAttr.includes(filterType);
    });
    
    if (clickedButton) {
        clickedButton.classList.add('active');
    }
    
    // Get all event cards
    const events = document.querySelectorAll('.event-card');
    
    events.forEach(event => {
        switch(filterType) {
            case 'pending':
                event.style.display = event.classList.contains('event-pending') ? 'block' : 'none';
                break;
            case 'approved':
                event.style.display = event.classList.contains('event-approved') ? 'block' : 'none';
                break;
            case 'admin':
                const isAdmin = event.getAttribute('data-is-admin') === '1';
                event.style.display = isAdmin ? 'block' : 'none';
                break;
            case 'user':
                const isUser = event.getAttribute('data-is-admin') === '0';
                event.style.display = isUser ? 'block' : 'none';
                break;
            default:
                event.style.display = 'block';
        }
    });
}

// Show reservation details in modal (optional)
function showReservationDetails(eventCard) {
    const venue = eventCard.querySelector('.event-venue').innerText;
    const time = eventCard.querySelector('.event-time').innerText;
    const activity = eventCard.querySelector('.event-activity').innerText;
    const manager = eventCard.querySelector('.event-manager').innerText;
    const reservedBy = eventCard.querySelector('.event-reserved-by').innerText;
    
    // You can replace this with a modal dialog
    alert(`Reservation Details:\n\n${venue}\n${time}\n${activity}\n${manager}\n${reservedBy}`);
}

// Initialize calendar functionality
$(document).ready(function() {
    // Add click event to event cards
    $('.event-card').click(function() {
        showReservationDetails(this);
    });
    
    // Optional: Add keyboard navigation
    $(document).keydown(function(e) {
        if (e.key === 'ArrowLeft') {
            const prevBtn = $('.calendar-nav-btn:first').attr('href');
            if (prevBtn) window.location.href = prevBtn;
        } else if (e.key === 'ArrowRight') {
            const nextBtn = $('.calendar-nav-btn:last').attr('href');
            if (nextBtn) window.location.href = nextBtn;
        }
    });
});