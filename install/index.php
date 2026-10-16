<?php
    error_reporting(E_ALL);
    $action = isset($_GET['action']) ? $_GET['action'] : 'requirements';
    if (!in_array($action, ['requirements', 'information', 'result'], true)) {
        $action = 'requirements';
    }
    function appUrl() {
    $current = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $exp     = explode('?action', $current);
    $url     = str_replace('index.php', '', $exp[0]);
    $url     = substr($url, 0, -8);
    return $url;
    }
    if ($action == 'requirements') {
    $passed      = [];
    $failed      = [];
    $requiredPHP = 8.3;
    $currentPHP  = explode('.', PHP_VERSION)[0] . '.' . explode('.', PHP_VERSION)[1];
    if ($requiredPHP == $currentPHP) {
        $passed[] = "PHP version $requiredPHP is required";
    } else {
        $failed[] = "PHP version $requiredPHP is required. Your current PHP version is $currentPHP";
    }
    $extensions = ['BCMath', 'Ctype', 'DOM', 'Fileinfo', 'GD', 'JSON', 'Mbstring', 'OpenSSL', 'PCRE', 'PDO', 'pdo_mysql', 'Tokenizer', 'XML', 'Filter', 'Hash', 'Session', 'zip'];
    foreach ($extensions as $extension) {
        if (extension_loaded($extension)) {
            $passed[] = strtoupper($extension) . ' PHP Extension is required';
        } else {
            $failed[] = strtoupper($extension) . ' PHP Extension is required';
        }
    }
    if (file_get_contents(__FILE__)) {
        $passed[] = 'file_get_contents() is required';
    } else {
        $failed[] = 'file_get_contents() is required';
    }
    $dirs = ['../core/bootstrap/cache/', '../core/storage/', '../core/storage/app/', '../core/storage/framework/', '../core/storage/logs/'];
    foreach ($dirs as $dir) {
        $perm = substr(sprintf('%o', fileperms($dir)), -4);
        if ($perm >= '0775') {
            $passed[] = str_replace("../", "", $dir) . ' is required 0775 permission';
        } else {
            $failed[] = str_replace("../", "", $dir) . ' is required 0775 permission. Current Permisiion is ' . $perm;
        }
    }
    if (file_exists('database.sql')) {
        $passed[] = 'database.sql should be available';
    } else {
        $failed[] = 'database.sql should be available';
    }
    if (file_exists('../.htaccess')) {
        $passed[] = '".htaccess" should be available in root directory';
    } else {
        $failed[] = '".htaccess" should be available in root directory';
    }
    }

    if ($action == 'result') {
        $response = ['error' => 'ok', 'message' => ''];
        try {
            $db = new PDO(
                'mysql:host=' . $_POST['db_host'] . ';dbname=' . $_POST['db_name'] . ';charset=utf8mb4',
                $_POST['db_user'],
                $_POST['db_pass'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $dbinfo = $db->query('SELECT VERSION()')->fetchColumn();
            $isMariaDb = stripos($dbinfo, 'mariadb') !== false;
            $version = implode('.', array_slice(explode('.', $dbinfo), 0, 2));
            $minimumVersion = $isMariaDb ? '10.6' : '8.0';
            if (!version_compare($version, $minimumVersion, '>=')) {
                throw new RuntimeException(($isMariaDb ? 'MariaDB 10.6+' : 'MySQL 8.0+') . ' is required. Current version: ' . $version);
            }

            $query = file_get_contents(__DIR__ . '/database.sql');
            if ($query === false) {
                throw new RuntimeException('Unable to read the installer database file.');
            }
            $db->exec($query);

            $envPath = __DIR__ . '/../core/.env';
            $envBody = file_get_contents(__DIR__ . '/../core/.env.example');
            if ($envBody === false) {
                throw new RuntimeException('Unable to read core/.env.example.');
            }
            $envValue = static function ($value) {
                return '"' . addcslashes((string) $value, "\\\"") . '"';
            };
            $envValues = [
                'APP_NAME' => $envValue('Xaxino'),
                'APP_ENV' => 'production',
                'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
                'APP_DEBUG' => 'false',
                'APP_URL' => $envValue(rtrim($_POST['url'], '/')),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $envValue($_POST['db_host']),
                'DB_PORT' => '3306',
                'DB_DATABASE' => $envValue($_POST['db_name']),
                'DB_USERNAME' => $envValue($_POST['db_user']),
                'DB_PASSWORD' => $envValue($_POST['db_pass'] ?? ''),
            ];
            foreach ($envValues as $key => $value) {
                $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
                $replacement = $key . '=' . $value;
                if (preg_match($pattern, $envBody)) {
                    $envBody = preg_replace_callback($pattern, static function () use ($replacement) {
                        return $replacement;
                    }, $envBody, 1);
                } else {
                    $envBody .= "\n" . $replacement;
                }
            }
            if (file_put_contents($envPath, $envBody) === false) {
                throw new RuntimeException('Unable to write core/.env. Check the directory permissions.');
            }

            $admin = $db->prepare('UPDATE admins SET email = ?, username = ?, password = ? WHERE username = ?');
            $admin->execute([$_POST['email'], $_POST['admin_user'], password_hash($_POST['admin_pass'], PASSWORD_DEFAULT), 'admin']);
        } catch (Throwable $e) {
            $response['error'] = 'error';
            $response['message'] = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
    $sectionTitle = $action == 'requirements' ? 'Requirements Check' : ucfirst($action);
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>Xaxino Installer</title>
	<link rel="stylesheet" href="../assets/global/css/bootstrap.min.css">
	<link rel="stylesheet" href="../assets/global/css/all.min.css">
	<link rel="stylesheet" href="../assets/global/css/installer.css">
</head>

<body>
	<header class="py-3 border-bottom border-primary bg--dark">
		<div class="container">
			<div class="d-flex align-items-center justify-content-between header gap-3">
				<h3 class="title">Xaxino Installer</h3>
			</div>
		</div>
	</header>
	<div class="installation-section padding-bottom padding-top">
		<div class="container">
			<div class="installation-wrapper">
				<div class="install-content-area">
					<div class="install-item">
						<h3 class="title text-center"><?php echo $sectionTitle; ?></h3>
						<div class="box-item">
							<?php
                                if ($action == 'result') {
                                    echo '<div class="success-area text-center">';
                                    if (isset($response['error']) && $response['error'] == 'ok') {
                                        echo '<h2 class="text-success text-uppercase mb-3">Your system has been installed successfully!</h2>';
                                        if (isset($response['message']) && $response['message']) {
                                            echo '<h5 class="text-warning mb-3">' . $response['message'] . '</h5>';
                                        }
                                        echo '<p class="text-danger lead my-5">Please delete the "install" folder from the server.</p>';
                                        echo '<div class="warning"><a href="' . appUrl() . '" class="theme-button choto">Open website</a></div>';
                                    } else {
                                        if (isset($response['message']) && $response['message']) {
                                            echo '<h3 class="text-danger mb-3">' . $response['message'] . '</h3>';
                                        } else {
                                            echo '<h3 class="text-danger mb-3">Your Server is not Capable to Handle the Request.</h3>';
                                        }
                                        echo '<div class="warning mt-2"><h5 class="mb-4 fw-normal">Check the details and try again.</h5><a href="?action=information" class="theme-button choto">Try Again</a></div>';

                                    }
                                    echo '</div>';
                                } else if ($action == 'information') {
                                ?>
								<form action="?action=result" method="post" class="information-form-area mb--20">
									<div class="info-item">
										<h5 class="font-weight-normal mb-2">Website URL</h5>
										<div class="row">
											<div class="information-form-group col-12">
												<input name="url" value="<?php echo appUrl(); ?>" type="text" required>
											</div>
										</div>
									</div>
									<div class="info-item">
										<h5 class="font-weight-normal mb-2">Database Details</h5>
										<div class="row">
											<div class="information-form-group col-sm-6">
												<input type="text" name="db_name" placeholder="Database Name" required>
											</div>
											<div class="information-form-group col-sm-6">
												<input type="text" name="db_host" placeholder="Database Host" required>
											</div>
											<div class="information-form-group col-sm-6">
												<input type="text" name="db_user" placeholder="Database User" required>
											</div>
											<div class="information-form-group col-sm-6">
												<input class="secure-password" type="text" name="db_pass" placeholder="Database Password">
											</div>
										</div>
									</div>
									<div class="info-item">
										<h5 class="font-weight-normal mb-3">Admin Credential</h5>
										<div class="row">
											<div class="information-form-group col-lg-3 col-sm-6">
												<label>Username</label>
												<input name="admin_user" type="text" placeholder="Admin Username" required>
											</div>
											<div class="information-form-group col-lg-3 col-sm-6">
												<label>Password</label>
												<input name="admin_pass" type="text" placeholder="Admin Password" required>
											</div>
											<div class="information-form-group col-lg-6">
												<label>Email Address</label>
												<input name="email" placeholder="Your Email address" type="email" required>
											</div>
										</div>
									</div>
									<div class="info-item">
										<div class="information-form-group text-end">
											<button type="submit" class="theme-button choto">Install Now</button>
										</div>
									</div>
								</form>
							<?php
                                } else if ($action == 'requirements') {
                                    $btnText = 'View Detailed Check Result';
                                    if (count($failed)) {
                                        $btnText = 'View Passed Check';
                                        echo '<div class="item table-area"><table class="requirment-table">';
                                        foreach ($failed as $fail) {
                                            echo "<tr><td>$fail</td><td><i class='fas fa-times'></i></td></tr>";
                                        }
                                        echo '</table></div>';
                                    }
                                    if (!count($failed)) {
                                        echo '<div class="text-center"><i class="far fa-check-circle success-icon text-success"></i><h5 class="my-3">Requirements Check Passed!</h5></div>';
                                    }
                                    if (count($passed)) {
                                        echo '<div class="text-center my-3"><button class="btn passed-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePassed" aria-expanded="false" aria-controls="collapsePassed">' . $btnText . '</button></div>';
                                        echo '<div class="collapse mb-4" id="collapsePassed"><div class="item table-area"><table class="requirment-table">';
                                        foreach ($passed as $pass) {
                                            echo "<tr><td>$pass</td><td><i class='fas fa-check'></i></td></tr>";
                                        }
                                        echo '</table></div></div>';
                                    }
                                    echo '<div class="item text-end mt-3">';
                                    if (count($failed)) {
                                        echo '<a class="theme-button btn-warning choto" href="?action=requirements">ReCheck <i class="fas fa-sync-alt"></i></a>';
                                    } else {
                                        echo '<a class="theme-button choto" href="?action=information">Next Step <i class="fas fa-angle-double-right"></i></a>';
                                    }
                                    echo '</div>';
                                } else {
                                ?>
								<div class="item">
									<h4 class="subtitle">Welcome to the Xaxino installer</h4>
									<p>Review the server requirements before entering your database and administrator details.</p>
									<a href="?action=requirements" class="theme-button choto">Check requirements</a>
								</div>
							<?php
                                }
                            ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<footer class="py-3 text-center bg--dark border-top border-primary">
		<div class="container">
			<p class="m-0 font-weight-bold">&copy;<?php echo Date('Y') ?> Xaxino</p>
		</div>
	</footer>
	<script src="../assets/global/js/bootstrap.bundle.min.js"></script>
</body>
</html>
