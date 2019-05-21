<?php

	require_once 'session_verification.php'; // programmer defined methods to prevent session hijacking

	verifySession(basename(__FILE__)); // check the session
?>

<!-- HTML Form -->
<!DOCTYPE html>

<html>
<head>
	<title>Infected File</title>
	<meta charset="utf-8">
 	<meta name="viewport" content="width=device-width, initial-scale=1">
  	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
  	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
  	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
  	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</head>

<center>
	<div>
	<form class="jumbotron jumbotron-fluid" >
		<div class="container" class="jumbotron jumbotron-fluid">
			<br><h1 class="display-4">VIRUS SCANNING</h1><br/><br/><br/><br/>
			<p class="lead">
			Infected File: It is a File that contains a Virus.<br/>
			Putative Infected File: It is a File that might contain the Virus and needs to go under analysis.
			</p>
		</div>
	</form>
	</div>

	<div class="col-5">
	<form action="file_check.php" method="post" enctype="multipart/form-data">
	<p>UPLOAD YOUR FILE TO CHECK:</p><br/>
		<div class="input-group">
			<div class="input-group-prepend">
				<span class="input-group-text" id="inputGroupFileAddon01">Upload</span>
			</div>
			<div class="custom-file">
			<input type="file" class="custom-file-input" name="check_file" required=""/>
			<label class="custom-file-label" for="inputGroupFile01">Choose File</label>
			</div>
		</div>
	</div>
	
	<br/><br/>
		
	<button type="submit" class="btn btn-outline-primary waves-effect">Submit</button>

	<br/><br/>
	</form>
	</div>
	<?php
		if (isset($_SESSION['admin'])) 
		{
			echo <<<_END
				<div><form class="col-md-5" action="file_check.php" method="post" enctype="multipart/form-data">
				<br/><font size="5"><b>Add Infected File Submission</b></font>
				<br/><br/>
				<input class="form-control" name="virus_name" type="text" placeholder="Virus Name" required=""><br/>
				<div class="custom-file">
					<input type="file" class="custom-file-input" name="add_virus" required=""/>
					<label class="custom-file-label">Choose Infected File</label>
				</div>
				<br/><br/>
				<button class="btn btn-outline-secondary waves-effect" type="submit">Submit Infected</button><br><br>
				</form></div><br>
_END;
		}
	?>
	<form>
		<br/><button type="submit" formaction="logout.php" class="btn btn-outline-danger waves-effect">Logout</button>
		<br/><br/>
	</form>
</center>
</html>

<?php
	require_once 'db_login.php'; 

	function fixString($conn, $string) {
		if (get_magic_quotes_gpc()) $string = stripslashes($string);
		return $conn->real_escape_string($string);
	}
									  
	if(isset($_FILES['check_file']['name']) && (isset($_SESSION['user']) || isset($_SESSION['admin'])))
	{
		$conn = new mysqli($hn, $un, $pw, $db);
		if ($conn->connect_error) mysqli_error($conn->connect_error);

		move_uploaded_file($_FILES['check_file']['tmp_name'], $_FILES['check_file']['name']);
		$sig = getSignatureHex($_FILES['check_file']['name'], $_FILES['check_file']['size']);
		scanFile($conn, $sig);

		$conn->close();
	}

	if(isset($_FILES['add_virus']['name']) && isset($_POST['virus_name']) && isset($_SESSION['admin']))
	{
		$conn = new mysqli($hn, $un, $pw, $db);
		if ($conn->connect_error) mysqli_error($conn->connect_error);

		$name = fixString($conn, $_POST['virus_name']);

		move_uploaded_file($_FILES['add_virus']['tmp_name'], $_FILES['add_virus']['name']);
		$sig = getSignatureHex($_FILES['add_virus']['name'], $_FILES['add_virus']['size']);

 		$sig = substr($sig, 0, 20);

 		add_virus($conn, $name, $sig);

		$conn->close();
	}

	function getSignatureHex($file, $size) 
	{
		$sig = "";

		if($handle = fopen($file, 'r')) 
		{
			$contents = fread($handle, $size);

			for ($i = 0; $i < $size; $i++) 
			{
				$ascii = $contents[$i];
				$dec = ord($ascii);
				$hex = base_convert($dec, 10, 16);
				$sig .= $hex;
			}
			return $sig;
		}
		else
			die("Problem with opening the file!");
	}

	function scanFile($conn, $sig)
	{
		$infected = false;
		$virus_list = array();
		$infected_bytes = $sig;
		$query = "SELECT * FROM virus;";
		$result = $conn->query($query);
		if (!$result) mysqli_error($conn->error);

		for ($i = 0; $i < $result->num_rows; $i++)
		{
			$result->data_seek($i);
			$row = $result->fetch_array(MYSQLI_NUM);
			$virus_name = $row[0];
			$virus_sig = $row[1];
			if (strlen($sig) >= strlen($virus_sig))
			{
				if (!empty($virus_sig) && strpos($sig, $virus_sig) !== false) { // infected file
   					echo '<script type="text/javascript">alert("This file is infected! The virus name is ' . $virus_name . '"); </script>';					
   					array_push($virus_list, array($virus_name, $virus_sig));
					$infected_bytes = markInfectedBytes($virus_sig, $infected_bytes);
					$infected = true; 
				}
			}
		}

		if(!$infected)
			echo '<script> alert("Secure file"); window.location = "file_check.php"; </script>';
		showInfectedFileResults($infected_bytes, $virus_list);
		$result->close();
	}

	function virusExists($conn, $name, $sig)
	{

		if ($result = $conn->prepare("SELECT * FROM virus WHERE name=? AND signature=?;")) { // create a prepare statement
			$result->bind_param('ss', $name, $sig);
			$result->execute();
			$result->store_result();
		}
		else 
			mysqli_error($conn->error);
		
		if ($result->num_rows == 1) {
			$result->close();
			return true;
		}

		$result->close();		
		
		return false;
	}

	function add_virus($conn, $name, $sig)
	{
		if (virusExists($conn, $name, $sig)) {
			echo '<script> alert("Duplicate record"); window.location = "file_check.php"; </script>';
			exit;
		}

 		$date  = date("Y-m-d");
		$time  = date("H:i:s");

		if ($result = $conn->prepare("INSERT INTO virus(name, signature, date, time) VALUES(?,?,?,?)")) {
			$result->bind_param('ssss', $name, $sig, $date, $time);
			$result->execute();
			$result->close();
			echo '<script> alert("Virus added"); </script>';
			showRecordedInserted($conn, $name, $sig);
		}
		else
			mysqli_error($conn->error); 	
	}

	function showRecordedInserted($conn, $name, $sig)
	{

		if ($result = $conn->prepare("SELECT * FROM virus WHERE name=? AND signature=?;")) {
			$result->bind_param('ss', $name, $sig); 
			$result->execute(); 
			$result->store_result(); 
		}
		else 
			mysqli_error($conn->error);
		if ($result->num_rows == 1) {
			$result->bind_result($name, $sig, $date, $time, $id); 
			$result->fetch(); 
			echo "<center><b>Added to table Virus in CS174 DB</b></center><br/><br/>";
			echo "<center><table>
			<tr>
			<th><center>Virus Name</center></th>
			<th><center>Virus Signature</center></th>
			<th><center>Date</center></th>
			<th><center>Time</center></th>
			<th><center>ID</center></th>
			</tr>";
			echo "<td><center>" . $name . "</center></td>";
		    echo "<td><center>" . $sig . "</center></td>";
		    echo "<td><center>" . $date . "</center></td>";
		    echo "<td><center>" . $time . "</center></td>";
		    echo "<td><center>" . $id . "</center></td>";
		    echo "</tr>";
		    echo "</table></center><br><br><br>";
		}
		else 
			mysqli_error($conn->error);
	}

	function markInfectedBytes($find, $string)
	{
		$replace = "<font color='red'>$find</font>";
		return str_replace($find, $replace, $string);
	}


	function showInfectedFileResults($infected_bytes, $list)
	{
		echo "<center><font color='red'><b>Infected Bytes:</b></font></center><br>";
		echo $infected_bytes; echo "<br><br>";
		echo "<center><font color='red'><b>" . count($list) . " Matches Found:</b></font></center><br>";
		echo "<center><table>
		<tr>
		<th><center>Virus Name</center></th>
		<th><center>Virus Signature</center></th>
		</tr>";

		foreach($list as $row) {
			echo "<tr>";

			foreach($row as $value)
			    echo "<td><center>" . $value . "</center></td>";

		    echo "</tr>";
		}

		echo "</table></center><br><br><br>";
	}
?>