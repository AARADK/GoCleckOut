<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Update Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
  
<?php include '../header.php' ?> 

<div class="container my-5">
  <div class="card p-4 mx-auto" style="max-width: 800px;">
    <h5 class="fw-bold mb-4">Update Profile</h5>

    <div class="mb-4">
      <label class="form-label">Profile photo</label>
      <div class="d-flex align-items-center gap-3">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/99/Sample_User_Icon.png" width="80" height="80" alt="User Avatar" class="border rounded">
        <div>
          <p class="mb-1">Upload your photo</p>
          <p class="text-muted small">Your photo should be in PNG or JPG format</p>
          <div class="d-flex align-items-center gap-2">
            <input class="form-control form-control-sm w-auto" type="file" aria-label="Upload photo">
            <button type="button" class="btn btn-link p-0 text-muted small">Remove</button>
          </div>
        </div>
      </div>
    </div>
    <div class="mb-3">
      <label for="fullname" class="form-label">Full Name</label>
      <input type="text" class="form-control" id="fullname" placeholder="Your full name">
    </div>
    <div class="mb-3">
      <label for="email" class="form-label">Email</label>
      <input type="email" class="form-control" id="email" placeholder="Your email">
    </div>
    <div class="mb-3">
      <label for="phone" class="form-label">Phone Number</label>
      <input type="text" class="form-control" id="phone" placeholder="Your phone number">
    </div>
    <div class="mb-4">
      <label for="dob" class="form-label">Date of Birth</label>
      <input type="date" class="form-control" id="dob">
    </div>
    <div class="d-flex justify-content-end gap-3">
      <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">Cancel</button>
      <button type="submit" class="btn btn-danger">Save Profile</button>
    </div>
  </div>
</div>

<?php include '../footer.php' ?> 

</body>
</html>
