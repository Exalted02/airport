document.addEventListener("click", function (e) {
    const btn = e.target.closest("#delete-account-btn, a[href='#delete-account']");
    if (!btn) return;

    e.preventDefault();

    if (confirm("Czy na pewno chcesz usunąć konto? Tej operacji nie można cofnąć.")) {

        fetch(csDeleteAccount.ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "action=cs_delete_user_account&nonce=" + csDeleteAccount.nonce
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Twoje konto zostało usunięte.");
                window.location.href = "/";
            } else {
                alert("Błąd: " + data.data);
            }
        });
    }
});
