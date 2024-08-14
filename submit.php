<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include the database connection file
include 'db.php';

// Fetch the username from the database using the user_id stored in the session
$user_id = $_SESSION['user_id'];
$sql = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("Error: User not found.");
}

$username = $user['username'];  // Username of the person submitting the file

function handleUpload($username) {
    $initial_directory = 'Uploads/DPX_IMAGE/';

    // Get today's date components
    $year = date('Y');
    $month = date('m_F');
    $day = date('d');

    // Build the directory path
    $current_directory = $initial_directory . "$year/$month/$day/";

    // Create the directory if it doesn't exist
    if (!file_exists($current_directory)) {
        mkdir($current_directory, 0777, true);
    }

    $response = array('success' => false, 'messages' => array());

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $date = isset($_POST['date']) ? $_POST['date'] : '';
        $name = isset($_POST['name']) ? $_POST['name'] : '';
        $tenant = isset($_POST['tenant']) ? $_POST['tenant'] : '';
        $capturedImage = isset($_POST['captured_image']) ? $_POST['captured_image'] : '';

        if (empty($date) || empty($name) || empty($tenant) || empty($capturedImage)) {
            $response['messages'][] = "Error: Date, Name, Tenant, or Captured Image is missing.";
        } else {
            // Decode the Base64 image data and save it as a file
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $capturedImage));
            $filename = "$date-$name-$tenant-$username.png";
            $filePath = rtrim($current_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

            if (file_put_contents($filePath, $imageData)) {
                $response['messages'][] = "The image $filename has been uploaded.";
                $response['success'] = true;
            } else {
                $response['messages'][] = "Sorry, there was an error uploading $filename.";
            }
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    return $current_directory;
}

$current_directory = handleUpload($username);
$last_directory = basename($current_directory);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Camera Upload</title>
    <link href="styleupload.css" rel="stylesheet" type="text/css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
<div class="upload-container">
    <div class="welcome-message">
        Welcome, <?= htmlspecialchars($username); ?>!
        <a href="logout.php" class="logout-button" style="float: right; padding: 5px 10px; background-color: #f44336; color: white; text-decoration: none; border-radius: 5px;">Logout</a>
    </div>
    <h1>Upload Files to <?= htmlspecialchars($last_directory) ?></h1>

    <div class="camera-container">
        <video id="video" autoplay></video>
        <button id="capture" type="button">Capture Photo</button>
    </div>
    <canvas id="canvas" style="display:none;"></canvas>
    <input type="hidden" name="captured_image" id="captured_image">

    <!-- Display captured image -->
    <div id="captured-image-container" style="display:none;">
        <h3>Captured Image:</h3>
        <img id="captured-image" src="" alt="Captured Image" style="max-width: 100%; height: auto;">
    </div>

    <form id="uploadForm" action="?directory=<?= urlencode($current_directory) ?>" method="post">
        <label for="date">Select Date:</label>
        <input type="date" id="date" name="date" required readonly>

        <label for="name">Customer Name:</label>
        <input type="text" id="name" name="name" required>

        <label for="tenant-search">Tenant:</label>
        <div class="tenant-search">
            <input type="text" id="tenant-search" placeholder="Search tenants...">
            <div class="tenant-list" id="tenant-list"></div>
        </div>
        <div id="selected-tenants"></div>

        <input type="hidden" id="tenant" name="tenant">

        <input type="submit" value="Submit Photo" name="submit">
    </form>
    <div id="notification" class="notification"></div>
</div>

<script>
$(document).ready(function() {
    var video = document.getElementById('video');
    var canvas = document.getElementById('canvas');
    var captureButton = document.getElementById('capture');
    var capturedImageInput = document.getElementById('captured_image');
    var capturedImageContainer = document.getElementById('captured-image-container');
    var capturedImage = document.getElementById('captured-image');
    var notification = $('#notification');
    var selectedTenants = [];

    // Access the device camera and stream to video element
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true }).then(function(stream) {
            video.srcObject = stream;
            video.play();
        });
    }

    // Capture the image when the button is clicked
    captureButton.addEventListener('click', function() {
        var context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        var imageDataURL = canvas.toDataURL('image/png');
        capturedImageInput.value = imageDataURL;

        // Display the captured image
        capturedImage.src = imageDataURL;
        capturedImageContainer.style.display = 'block';

        // Show a notification
        notification.removeClass('error').addClass('success').html('Picture successfully captured!').slideDown();

        setTimeout(function() {
            notification.slideUp(); // Hide the notification after a few seconds
        }, 3000); // Hide after 3 seconds
    });

    // Fetch all tenants initially
    fetchTenants('');

    // Listen for input in the search box
    $('#tenant-search').on('keyup', function() {
        var query = $(this).val();
        fetchTenants(query);
    });

    // Function to fetch tenants from the server
    function fetchTenants(query) {
        $.ajax({
            url: 'fetch_tenants.php',
            method: 'GET',
            data: { query: query },
            dataType: 'json',
            success: function(response) {
                var tenantList = $('#tenant-list');
                tenantList.empty();

                if (response.length > 0) {
                    response.forEach(function(tenant) {
                        tenantList.append('<li data-name="' + tenant.name + '">' + tenant.name + '</li>');
                    });
                } else {
                    tenantList.append('<li>No results found</li>');
                }
            }
        });
    }

    // Handle tenant selection from the list
    $(document).on('click', '#tenant-list li', function() {
        var tenantName = $(this).data('name');

        if (!selectedTenants.includes(tenantName)) {
            selectedTenants.push(tenantName);
            $('#selected-tenants').append('<div data-name="' + tenantName + '">' + tenantName + ' <span class="remove-tenant" style="cursor:pointer;color:#ff3b30;">&times;</span></div>');
            updateTenantInput();
        }

        $('#tenant-list').empty();
        $('#tenant-search').val('');
    });

    // Handle tenant removal from the selected list
    $(document).on('click', '.remove-tenant', function() {
        var tenantDiv = $(this).parent();
        var tenantName = tenantDiv.data('name');

        selectedTenants = selectedTenants.filter(function(name) {
            return name !== tenantName;
        });

        tenantDiv.remove();
        updateTenantInput();
    });

    // Update the hidden input with selected tenant names separated by underscores
    function updateTenantInput() {
        $('#tenant').val(selectedTenants.join('_'));
    }

    // Set the date input to today's date and make it read-only
    var today = new Date().toISOString().split('T')[0];
    $('#date').val(today);

    // Handle form submission for file upload
    $('#uploadForm').on('submit', function(e) {
        e.preventDefault();  // Prevent the default form submission

        var date = $('#date').val();
        var name = $('#name').val();
        var tenant = $('#tenant').val();

        if (!date || !name || !tenant) {
            alert("Please fill out all fields.");
            return;
        }

        var capturedImage = $('#captured_image').val();

        if (capturedImage) {
            uploadCapturedImage(date, name, tenant, capturedImage);
        } else {
            alert("Please capture a photo first.");
        }
    });

    // Function to upload the captured image
    function uploadCapturedImage(date, name, tenant, capturedImage) {
        var formData = new FormData();
        formData.append('date', date);
        formData.append('name', name);
        formData.append('tenant', tenant);
        formData.append('captured_image', capturedImage);

        $.ajax({
            url: $('#uploadForm').attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                var notification = $('#notification');
                if (response.success) {
                    notification.removeClass('error').addClass('success').html('Successfully uploaded.').slideDown();
                    // Reset the form fields
                    $('#name').val('');
                    $('#tenant-search').val('');
                    $('#selected-tenants').empty();
                    selectedTenants = [];
                    updateTenantInput();
                } else {
                    notification.removeClass('success').addClass('error').html(response.messages.join('<br>')).slideDown();
                }
                setTimeout(function() {
                    notification.slideUp(); // Hide the notification after a few seconds
                }, 5000);
            },
            error: function(xhr, status, error) {
                notification.removeClass('success').addClass('error').html('Error: ' + error).slideDown();
                setTimeout(function() {
                    notification.slideUp(); // Hide the notification after a few seconds
                }, 5000);
            }
        });
    }
});
</script>

</body>
</html>


