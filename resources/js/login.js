const loginForm = document.getElementById("loginForm");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const errorBox = document.getElementById("errorBox");
const submitBtn = document.querySelector(".btn-login");

if (!loginForm || !emailInput || !passwordInput || !errorBox) {
    console.error("Login form elements missing — page may be incorrectly rendered.");
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Sign In"; }
}

loginForm.addEventListener("submit", function (e) {
    const email = emailInput.value.trim();
    const password = passwordInput.value;

    errorBox.innerHTML = "";
    errorBox.classList.remove("error-box--visible");

    if (!email || !password) {
        e.preventDefault();
        errorBox.innerHTML = "<p>Email and password are required</p>";
        errorBox.classList.add("error-box--visible");
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Signing in...";
    }
});
