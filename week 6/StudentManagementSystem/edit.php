<?php
include("db.php");

$id=$_GET['id'];

$result=mysqli_query($conn,
"SELECT * FROM students WHERE id=$id");

$row=mysqli_fetch_assoc($result);


if(isset($_POST['update'])){

$name=$_POST['fullname'];
$email=$_POST['email'];
$course=$_POST['course'];


mysqli_query($conn,
"UPDATE students SET 
fullname='$name',
email='$email',
course='$course'
WHERE id=$id");


echo "Student Updated Successfully";

}

?>


<!DOCTYPE html>
<html>
<head>
<title>Edit Student</title>
</head>

<body>

<h2>Edit Student</h2>


<form method="POST">

Name:
<input type="text" 
name="fullname"
value="<?php echo $row['fullname']; ?>">
<br><br>


Email:
<input type="email"
name="email"
value="<?php echo $row['email']; ?>">
<br><br>


Course:
<input type="text"
name="course"
value="<?php echo $row['course']; ?>">
<br><br>


<button name="update">
Update
</button>


</form>


<br>

<a href="index.php">Back</a>


</body>
</html>