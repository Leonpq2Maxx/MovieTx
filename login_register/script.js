function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(form => {
        form.style.display = "none";
    });

    const el = document.getElementById(formId);
    if (el) {
        el.style.display = "block";
    }
}


function showForm(id) {
    document.querySelectorAll('.form-box').forEach(f => {
        f.style.display = 'none';
    });
    document.getElementById(id).style.display = 'block';
}

