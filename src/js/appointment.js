// Wait for the HTML document to be fully loaded before running the script
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Find all buttons with the 'timeslot-btn' class
    const timeslotButtons = document.querySelectorAll(".timeslot-btn");

    // 2. Loop through each button and add a click event listener
    timeslotButtons.forEach(button => {
        button.addEventListener("click", function() {
            
            // 3. Check if the clicked button is disabled
            // If it is, do nothing.
            if (this.classList.contains("disabled")) {
                return;
            }

            // 4. If it's not disabled, first remove the 'selected' 
            //    class from ALL timeslot buttons
            timeslotButtons.forEach(btn => {
                btn.classList.remove("selected");
            });

            // 5. Finally, add the 'selected' class ONLY 
            //    to the button that was just clicked
            this.classList.add("selected");
        });
    });

    // --- NEW: Doctor Dropdown Code ---

    // 1. Get all the necessary elements
    const doctorSelectorCard = document.querySelector(".doctor-selector");
    const dropdownMock = document.querySelector(".dropdown-mock");
    const dropdownContent = document.querySelector(".dropdown-content-wrapper");
    const doctorList = document.querySelector(".doctor-list");
    const doctorItems = document.querySelectorAll(".doctor-item");

    // 2. Add click event to the main dropdown box to toggle it
    dropdownMock.addEventListener("click", () => {
        doctorSelectorCard.classList.toggle("open");
    });

    // 3. Add click events to each *item* in the list
    doctorItems.forEach(item => {
        item.addEventListener("click", () => {
            
            // A. Copy the *entire* .doctor-item element (not just its contents)
            dropdownContent.innerHTML = item.outerHTML;
            
            // B. Add a 'has-selection' class to the parent card
            doctorSelectorCard.classList.add("has-selection");

            // C. Close the dropdown list
            doctorSelectorCard.classList.remove("open");
        });
    });

    // Optional: Close dropdown if clicking outside of it
    window.addEventListener('click', function(e) {
        // If the click is *not* inside the doctorSelectorCard
        if (!doctorSelectorCard.contains(e.target)) {
            doctorSelectorCard.classList.remove('open');
        }
    });

});