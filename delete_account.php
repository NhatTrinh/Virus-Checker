<!-- HTML Login Form -->
<!DOCTYPE html>
<html>
	<title>Delete Account</title>
	<link rel = "stylesheet" type = "text/css" href = "index_css.css">
	<form  method="post">
		<input name="email"    type="email"    placeholder="email"    maxlength="128" required="" />
		<input name="password" type="password" placeholder="password" maxlength="128" required="" />
		<button type="submit" formaction="delete_account.php">delete</button>	
	<form>
</html>


<?php
	require_once 'db_login.php'; 
	require_once 'mysql_methods.php'; 
	require_once 'session_verification.php';

	verify_session(basename(__FILE__)); // check the session

	function fixString($conn, $string) {
		if (get_magic_quotes_gpc()) $string = stripslashes($string);
		return $conn->real_escape_string($string);
	}

	if (isset($_POST['email']) && isset($_POST['password'])) {
		$conn = new mysqli($hn, $un, $pw, $db);
		if ($conn->connect_error) mysql_fatal_error($conn->connect_error);
		
		$un = fixString($conn, $_POST['email']); 
		$pw = fixString($conn, $_POST['password']);

		if ($result = $conn->prepare("SELECT id, fname, lname, username, password, salt1, salt2 FROM users NATURAL JOIN salt WHERE username=?;")) { 
			$result->bind_param('s', $un); 
			$result->execute(); 
			$result->store_result(); 
		}
		else 
			mysql_fatal_error($conn->error);

		if ($result->num_rows == 1) { 
			$result->bind_result($id, $fn, $sn, $un, $db_pw, $s1, $s2); 
			$result->fetch(); 

			$salt1 = $s1;
			$salt2 = $s2;
			$token = hash('md5', "$salt1$pw$salt2");

			if ($token === $db_pw) { 
				if ($del_user = $conn->prepare("DELETE FROM users WHERE id=?")) { 
					if($del_salt = $conn->prepare("DELETE FROM salt WHERE id=?")) { 
						$del_user->bind_param('i', $id);
						$del_user->execute();
						$del_user->close();
						$del_salt->bind_param('i', $id);
						$del_salt->execute();
						$del_salt->close();
					}
					else 
						mysql_fatal_error($conn->error);
				}
				else 
					mysql_fatal_error($conn->error);

				echo 
				'<script>
					alert("Hi: '. $fn . ', you\'ve successfully deleted your account '. $un . '"); 
					window.location = "index.php";
				</script>';
			}
			else {
				echo '<script> alert("Invalid username/password combination"); window.location = "delete_account.php"; </script>';
			}
		}
		else {
			echo '<script> alert("Invalid username/password combination"); window.location = "delete_account.php"; </script>';
		}
		$result->close(); 
		$conn->close(); 
	}
?>