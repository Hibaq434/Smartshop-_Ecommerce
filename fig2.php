<!DOCTYPE html>
<html>

<head>

    <title>Password Strength Checker</title>

    <style>

        body{
            font-family: Arial, sans-serif;
            margin: 40px;
        }

        h1{
            margin-bottom: 20px;
        }

        input{
            width: 250px;
            padding: 8px;
            margin-top: 10px;
        }

        #message{
            margin-top: 10px;
            font-weight: bold;
        }

        .weak{
            color: red;
        }

        .medium{
            color: orange;
        }

        .strong{
            color: green;
        }

    </style>

</head>

<body>

    <h1>Password Strength Checker</h1>

    <label>Enter Password:</label><br>

    <input type="password" id="password" onkeyup="checkStrength()">

    <p id="message"></p>

    <script>

        function checkStrength(){

            let password = document.getElementById("password").value;
            let message = document.getElementById("message");

            if(password.length < 4){

                message.innerHTML = "Weak Password";
                message.className = "weak";

            }

            else if(password.length >= 4 && password.length < 8){

                message.innerHTML = "Medium Password";
                message.className = "medium";

            }

            else{

                message.innerHTML = "Strong Password";
                message.className = "strong";

            }

        }

    </script>

</body>

</html>