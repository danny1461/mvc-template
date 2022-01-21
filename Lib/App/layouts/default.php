<?php
	/** @var \Lib\Render\IViewHelpers $this */
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
	$this->outputHead();
	?>
</head>
<body class="<?php echo $this->bodyClass() ?>">
	<?= $this->getContents() ?>
</body>
</html>