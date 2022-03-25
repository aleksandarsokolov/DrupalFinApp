window.addEventListener('load', (event) => {
    console.log('page is fully loaded');
    var verifiedBills = document.getElementsByClassName("verified-bill");
    Array.from(verifiedBills).forEach(child => {
        console.log(child);
        var closest = child.closest("tr");
        if (closest) {    
            closest.classList.add('verified-bill-tr');
        }
    });
});