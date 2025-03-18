<?php
/**
 * Plugin Name: Admin Image Resizer
 * Description: A plugin that allows admins to upload multiple images, and resize them based on user input.
 * Version: 1.1
 * Author: Quin Verhoeven
 */

// Ensure the script is only executed in the admin area
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue plugin's styles
function enqueue_admin_styles() {
    wp_enqueue_style('admin-image-resizer-styles', plugin_dir_url(__FILE__) . 'css/style.css');
}
add_action('admin_enqueue_scripts', 'enqueue_admin_styles');

// Add the custom page
function resize_form_page() {
    if (current_user_can('administrator')) { // Ensure only admins can see the form
        ?>
        <div class="wrap">
            <h1>Resize Tool</h1>

            <form method="post" enctype="multipart/form-data">
                <label for="image_upload">Select Images:</label>
                <input type="file" name="image_upload[]" id="image_upload" multiple>

                <label for="resize_factor">Resize Factor (e.g., 2 for 2x smaller):</label>
                <input type="number" name="resize_factor" id="resize_factor" step="0.1" min="0.1" value="1">

                <input type="submit" name="submit_form" value="Upload and Resize" class="button button-primary">
            </form>

            <!-- Loading Circle -->
            <div class="loader" id="loadingCircle"></div>

            <?php
            // Process uploaded images if there are any
            if (isset($_POST['submit_form'])) {
                handle_image_upload_and_resize();
            }
            ?>
        </div>

        <style>
            .loader {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                border: 8px solid #f3f3f3;
                border-top: 8px solid #3498db;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { transform: translate(-50%, -50%) rotate(0deg); }
                100% { transform: translate(-50%, -50%) rotate(360deg); }
            }
        </style>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let form = document.querySelector("form");
                let loader = document.getElementById("loadingCircle");

                form.addEventListener("submit", function() {
                    loader.style.display = "block";
                });
            });
        </script>

        <?php
    } else {
        echo '<p>You do not have permission to access this page.</p>';
    }
}

function custom_admin_menu() {
    add_menu_page('Resize tool form', 'Resize tool', 'administrator', 'resize-tool', 'resize_form_page');
}
add_action('admin_menu', 'custom_admin_menu'); // Add action to bar

// Handle image upload and resize
function handle_image_upload_and_resize() {
    if (isset($_FILES['image_upload']) && !empty($_FILES['image_upload']['name']) && isset($_POST['resize_factor']) && current_user_can('administrator')) {
        $resize_factor = floatval($_POST['resize_factor']); // Get the resize factor input from the user

        // Loop through each uploaded file
        $file_count = count($_FILES['image_upload']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $uploaded_file = array(
                'name'     => $_FILES['image_upload']['name'][$i],
                'type'     => $_FILES['image_upload']['type'][$i],
                'tmp_name' => $_FILES['image_upload']['tmp_name'][$i],
                'error'    => $_FILES['image_upload']['error'][$i],
                'size'     => $_FILES['image_upload']['size'][$i],
            );

            $upload_result = wp_handle_upload($uploaded_file, array('test_form' => false));

            if (isset($upload_result['file'])) {
                // Get the uploaded image path
                $image_path = $upload_result['file'];
                $image_info = getimagesize($image_path);
                
                // Proceed only if the file is an image
                if ($image_info) {
                    // Resize the image based on the user's factor
                    $resized_image_path = resize_image($image_path, $resize_factor);

                    // Add the resized image to the WordPress Media Library
                    add_image_to_media_library($resized_image_path, $uploaded_file['name']);

                    echo '<div class="updated"><p>Image uploaded and resized successfully: ' . esc_html($uploaded_file['name']) . '</p></div>';
                } else {
                    echo '<div class="error"><p>Uploaded file is not a valid image: ' . esc_html($uploaded_file['name']) . '</p></div>';
                }
            }
        }
    } else {
        echo '<div class="error"><p>Please select files to upload and provide a resize factor.</p></div>';
    }
}

// Function to resize the image based on the user's resize factor
function resize_image($image_path, $resize_factor) {
    // Get the image's original dimensions
    list($width, $height) = getimagesize($image_path);

    // Calculate the new dimensions
    $new_width = round($width / $resize_factor);
    $new_height = round($height / $resize_factor);

    // Load the image using WordPress's image editor
    $editor = wp_get_image_editor($image_path);

    if (!is_wp_error($editor)) {
        $editor->resize($new_width, $new_height, false); // Resize maintaining aspect ratio
        $editor->save($image_path); // Save the resized image
    } else {
        echo '<div class="error"><p>Error resizing image: ' . esc_html($image_path) . '</p></div>';
    }

    return $image_path;
}

// Add the resized image to the WordPress Media Library
function add_image_to_media_library($image_path, $file_name) {
    // Get the file's mime type
    $mime_type = mime_content_type($image_path);

    // Prepare attachment data
    $attachment = array(
        'guid'           => wp_upload_dir()['url'] . '/' . basename($image_path),
        'post_mime_type' => $mime_type,
        'post_title'     => sanitize_file_name($file_name),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    // Insert the attachment
    $attachment_id = wp_insert_attachment($attachment, $image_path);
    
    // Generate attachment metadata
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $image_path);

    // Update metadata
    wp_update_attachment_metadata($attachment_id, $attachment_metadata);
}
