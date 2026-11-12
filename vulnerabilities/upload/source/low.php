
<?php

if (isset($_POST['Upload'])) {

    // 1. Verify that a file was received correctly
    if (!isset($_FILES['uploaded']) ||
        $_FILES['uploaded']['error'] !== UPLOAD_ERR_OK) {

        $html .= '<pre>Your image was not uploaded.</pre>';

    } else {

        $uploaded_tmp = $_FILES['uploaded']['tmp_name'];
        $uploaded_size = $_FILES['uploaded']['size'];

        // 2. Check the file size (100 KB maximum)
        if ($uploaded_size > 100000 ||
            !is_uploaded_file($uploaded_tmp)) {

            $html .= '<pre>Invalid file or file too large.</pre>';

        } else {

            // 3. Detect the real MIME type using Fileinfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $uploaded_tmp);
            finfo_close($finfo);

            // 4. Verify that the file is a valid JPEG or PNG image
            $image_info = @getimagesize($uploaded_tmp);

            $allowed_types = array(
                'image/jpeg' => 'jpg',
                'image/png'  => 'png'
            );

            if (
                $image_info === false ||
                !isset($allowed_types[$mime]) ||
                $image_info['mime'] !== $mime
            ) {

                $html .= '<pre>Only valid JPEG and PNG images are accepted.</pre>';

            } else {

                // 5. Limit image dimensions
                $width = $image_info[0];
                $height = $image_info[1];

                if (
                    $width > 5000 ||
                    $height > 5000 ||
                    ($width * $height) > 20000000
                ) {

                    $html .= '<pre>Image dimensions are too large.</pre>';

                } else {

                    // 6. Reprocess the image using GD
                    if ($mime === 'image/jpeg') {
                        $image = @imagecreatefromjpeg($uploaded_tmp);
                    } else {
                        $image = @imagecreatefrompng($uploaded_tmp);
                    }

                    if ($image === false) {

                        $html .= '<pre>Could not process the image.</pre>';

                    } else {

                        // 7. Generate a random filename
                        $extension = $allowed_types[$mime];
                        $random_name = bin2hex(random_bytes(16)) . '.' . $extension;

                        $target_file = $PHPUploadPath . $random_name;

                        // 8. Save the newly generated image
                        if ($mime === 'image/jpeg') {
                            $saved = @imagejpeg($image, $target_file, 90);
                        } else {
                            $saved = @imagepng($image, $target_file, 9);
                        }

                        imagedestroy($image);

                        if ($saved) {

                            $relative_path = DVWA_WEB_PAGE_TO_ROOT .
                                'hackable/uploads/' . $random_name;

                            $safe_link = htmlspecialchars(
                                $relative_path,
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            $html .= "<pre><a href='{$safe_link}'>Image uploaded successfully.</a></pre>";

                        } else {

                            $html .= '<pre>Your image could not be saved.</pre>';

                        }
                    }
                }
            }
        }
    }
}

?>
