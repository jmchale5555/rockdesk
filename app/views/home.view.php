<?php include 'partials/header.view.php' ?>

<article>
    <header>
        <h1>Welcome</h1>
        <p>Hello, <?= esc($name) ?>.</p>
    </header>

    <p>
        This is a simple PHP MVC monolith with server-rendered pages.
        Interactivity should stay light and local.
    </p>

    <?php if (empty($_SESSION['USER'])): ?>
        <p>
            <a href="<?= ROOT ?>/login"
                role="button"
                hx-get="<?= ROOT ?>/login"
                hx-target="#page-content"
                hx-select="#page-content > *"
                hx-select-oob="#site-nav"
                hx-swap="innerHTML"
                hx-push-url="true">Go to login</a>
        </p>
    <?php endif; ?>

    <figure>
        <img src="<?= esc($funk) ?>" alt="Welcome image">
    </figure>
</article>

<?php include 'partials/footer.view.php' ?>
