<?php
include("db.php");

if(isset($_POST['submit'])){

$name=$_POST['fullname'];
$email=$_POST['email'];
$course=$_POST['course'];

$sql="INSERT INTO students(fullname,email,course)
VALUES('$name','$email','$course')";

mysqli_query($conn,$sql);

echo "Student Added Successfully";
}
?>

<form method="POST">

<input type="text" name="fullname" placeholder="Full Name">

<input type="email" name="email" placeholder="Email">

<input type="text" name="course" placeholder="Course">

<button name="submit">Save Student</button>

</form>

<a href="index.php">View Students</a>