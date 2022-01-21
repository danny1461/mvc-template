<?php
	/** @var \Lib\Render\IViewHelpers $this */
	/** @var \App\ViewModels\Index\Index $model */

	use Lib\DI;
	use Lib\Request;
	use Lib\SimpleAlert;

	$request = DI::get(Request::class);
?><!DOCTYPE html>
<html dir="ltr" lang="en-US" class="html5 <?php echo $this->htmlClass() ?>">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo $this->pageTitle('Site Title') ?></title>
	<meta name="theme-color" content="#ff0000">
	<link rel="canonical" href="<?= $this->baseUrl($request) ?>">

	<script>
		function docReady(fn) {
			if (document.readyState != 'loading') {
				fn();
			} else {
				document.addEventListener('DOMContentLoaded', fn);
			}
		}
	</script>

	<?php
	$this->styleEnqueue('font-awesome', $this->publicUrl('css/font-awesome.min.css'));
	$this->styleRegister('fonts', 'https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600|Roboto:300,400,700');
	$this->styleRegister('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css');
	$this->styleEnqueue('style', $this->publicUrl('css/style.min.css'), ['fonts', 'bootstrap']);

	$this->scriptRegister('bootstrap.bundle', $this->publicUrl('js/bootstrap.bundle.min.js'));
	$this->scriptEnqueue('main', $this->publicUrl('js/main.min.js'), ['bootstrap.bundle']);

	$this->meta('og:site_name', 'Site Title');
	$this->meta('twitter:title', 'Site Title');

	$this->outputHead();

	// match the $collapse var in your _vars.scss file
	$collapse = "lg";
	?>
</head>

<body class="<?php echo $this->bodyClass() ?>">

	<header class="site-header">
		<nav class="navbar navbar-expand-<?= $collapse ?> navbar-light bg-light">
			<div class="container-fluid">
				<a class="navbar-brand" href="#">
					<img src="<?php echo $this->publicUrl('images/logo.png') ?>" class="img-fluid" alt="Go to home page">
				</a>

				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav" aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
					<span class="icon-bar"></span>
					<span class="icon-bar icon-bar-x"></span>
					<span class="icon-bar icon-bar-x icon-bar-sneaky"></span>
					<span class="icon-bar"></span>
					<span class="visually-hidden">Toggle navigation</span>
				</button>

				<div class="collapse navbar-collapse ml-lg-auto" id="main-nav">
					<ul class="navbar-nav mx-auto ms-<?= $collapse ?>-auto me-<?= $collapse ?>-0 mb-2 mb-lg-0">
						<li class="nav-item <?php echo $request->getActionName() == 'Index' ? 'active' : '' ?>">
							<a class="nav-link" aria-current="page" href="<?php echo $this->baseUrl() ?>">Home</a>
						</li>
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
								Dropdown
							</a>
							<ul class="dropdown-menu" aria-labelledby="navbarDropdown">
								<li><a class="dropdown-item" href="#">Action</a></li>
								<li><a class="dropdown-item" href="#">Another action</a></li>
							</ul>
						</li>
						<li class="nav-item cta <?php echo $request->getActionName() == 'subpage' ? 'active' : '' ?>">
							<a class="nav-link" href="<?php echo $this->baseUrl('subpage') ?>">Subpage</a>
						</li>
					</ul>
				</div>
			</div>
		</nav>
	</header>

	<?php foreach (SimpleAlert::getAndClearAlerts() as $alert) : ?>
		<div class="alert alert-<?php echo $alert['type'] ?>" role="alert">
			<?php echo $alert['html'] ? $alert['message'] : $this->escapeHtml($alert['message']) ?>
			<button type="button" class="close" data-dismiss="alert" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
	<?php endforeach; ?>

	<?php echo $this->getContents() ?>

	<footer class="py-3 bg-dark text-white">
		<div class="container">
			<!-- add your domain -->
			<div>Theme by <a href="https://www.dandi.dev" target="_blank" class="text-light">Dandi</a>.</div>
		</div>
	</footer>

<?php $this->outputFooter(); ?>
</body>
</html>