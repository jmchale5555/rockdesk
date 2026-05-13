<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ROOT ?>/assets/css/pico-2-1-1.min.css">
    <style>
        [x-cloak] {
            display: none !important;
        }

        .auth-panel {
            width: min(100%, 22rem);
            margin-inline: auto;
        }

        .nav-menu-wrap {
            position: relative;
        }

        .nav-menu-button {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0;
            padding: 0.4rem 0.65rem;
            border: 1px solid var(--pico-primary-border);
            background: var(--pico-primary-background);
            color: var(--pico-primary-inverse);
        }

        .nav-menu-button:hover,
        .nav-menu-button:focus-visible {
            border-color: var(--pico-primary-hover-border, var(--pico-primary-border));
            background: var(--pico-primary-hover-background, var(--pico-primary-background));
            color: var(--pico-primary-inverse);
        }

        .nav-menu-icon {
            display: block;
            filter: brightness(0) invert(1);
        }

        .nav-menu-panel {
            position: absolute;
            top: calc(100% + 0.35rem);
            right: 0;
            min-width: 12rem;
            padding: 0.35rem;
            border: 1px solid var(--pico-muted-border-color);
            border-radius: var(--pico-border-radius);
            background: var(--pico-card-background-color, var(--pico-background-color));
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.14);
            z-index: 20;
        }

        .nav-menu-panel a {
            display: block;
            border-radius: 0.4rem;
            padding: 0.45rem 0.65rem;
            text-decoration: none;
        }

        .nav-menu-panel a:hover,
        .nav-menu-panel a:focus-visible {
            background: var(--pico-primary-background);
            color: var(--pico-primary-inverse);
        }

        .password-input-wrap {
            position: relative;
        }

        .password-input-wrap input {
            padding-right: 2.5rem;
            margin-bottom: 0;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 0.5rem;
            transform: translateY(-50%);
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            box-shadow: none;
            line-height: 0;
            min-width: 0;
        }

        .password-toggle:hover,
        .password-toggle:focus-visible,
        .password-toggle:active {
            border: 0;
            background: transparent;
            box-shadow: none;
        }

        .password-toggle img {
            display: block;
            width: 18px;
            height: 18px;
            filter: brightness(0) saturate(100%) invert(95%) sepia(17%) saturate(221%) hue-rotate(327deg) brightness(103%) contrast(97%);
        }

        .auth-panel form>button[type="submit"] {
            margin-top: 0.9rem;
        }
    </style>
</head>

<body>
    <header class="container">
        <?php include 'navbar.view.php' ?>
    </header>
    <main id="page-content" class="container">
