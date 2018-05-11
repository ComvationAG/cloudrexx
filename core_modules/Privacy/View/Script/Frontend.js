document.addEventListener("DOMContentLoaded", function() {
    var cookieNote = document.getElementById("cookie-note");
    var cookieOk = document.getElementById("cookie-note-ok");
    if (!cookieNote || !cookieOk) {
        return;
    }
    var value = "; " + document.cookie;
    var parts = value.split("; ClxCookieNote=");
    if (
        parts.length == 2 &&
        parts.pop().split(";").shift() == "accepted"
    ) {
        cookieNote.style.display = "none";
    }
    cookieOk.addEventListener(
        "click",
        function () {
            document.cookie = "ClxCookieNote=accepted;"
            cookieNote.style.display = "none";
        }
    );
});
