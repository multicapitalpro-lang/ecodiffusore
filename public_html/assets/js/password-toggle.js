document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.password-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;

            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.classList.toggle('is-showing', !showing);
            btn.setAttribute('aria-label', showing ? 'Mostrar senha' : 'Ocultar senha');
        });
    });
});
