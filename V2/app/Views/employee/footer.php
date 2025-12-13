</main>
</div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Session Storage pour la sécurité -->
<script>
    // Si l'utilisateur clique sur déconnexion, on vide aussi le sessionStorage
    document.querySelectorAll('a[href*="logout"]').forEach(link => {
        link.addEventListener('click', () => {
            sessionStorage.removeItem('isLoggedIn');
        });
    });
</script>
</body>

</html>