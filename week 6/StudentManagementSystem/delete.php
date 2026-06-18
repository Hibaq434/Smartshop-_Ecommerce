<?php

include("db.php");

$id = $_GET['id'];

if (isset($_POST['delete'])) {

    $id = intval($_POST['id']);

    mysqli_query($conn, "DELETE FROM students WHERE id=$id");

    echo "
    <h3>Student Deleted Successfully</h3>
    <script>
        setTimeout(function(){
            window.location='index.php';
        },2000);
    </script>";

    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Delete Student</title>

<style>
    body{
        font-family: Arial;
        background:#f2f2f2;
        text-align:center;
        padding-top:100px;
    }

    .box{
        background:white;
        width:350px;
        margin:auto;
        padding:30px;
        border-radius:10px;
        box-shadow:0 0 10px gray;
    }

    button{
        padding:10px 20px;
        border:none;
        border-radius:5px;
        cursor:pointer;
        margin:10px;
    }

    .delete{
        background:red;
        color:white;
    }

    .cancel{
        background:gray;
        color:white;
    }

</style>

</head>

<body>

<div class="box">

<h2>Confirm Deletion</h2>

<p>Are you sure you want to delete this student?</p>

<form method="POST">

<input type="hidden" name="id" value="<?php echo $id; ?>">

<button class="delete" name="delete">
Yes, Delete
</button>

<a href="index.php">
<button type="button" class="cancel">
Cancel
</button>
</a>

</form>

</div>

</body>
</html>