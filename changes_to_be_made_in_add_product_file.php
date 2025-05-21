<!-- changes to be made in add_product.php file -->

<!-- add this part before the add product button 
 this is a button that is used to initiate the rfid scan
 this uses the same css as of the other button                -->
                <button type="button" class="submit-btn" id="enable-rfid">🔄 Scan via RFID</button>
                <p id="scan-status" style="color: green; display: none;">Scanning RFID...</p>

<!-- add this in the script part of the file -->

                // Trigger Python script via PHP backend
        fetch('trigger_rfid.php')
            .then(() => pollRFID());
        });

        function pollRFID() {
            if (!scanning) return;

            fetch('rfid_scan.json?' + new Date().getTime())
                .then(res => res.json())
                .then(data => {
                    if (data && data.data && data.data.product_name) {
                     // Fill the form
                        document.querySelector('[name="product_name"]').value = data.data.product_name;
                        document.querySelector('[name="description"]').value = data.data.description;
                        document.querySelector('[name="price"]').value = data.data.price;
                        document.querySelector('[name="stock"]').value = data.data.stock;
                        document.querySelector('[name="shop_id"]').value = data.data.shop_id;

                        document.getElementById('scan-status').textContent = "✔️ RFID scanned successfully!";
                        scanning = false;
                    } else {
                        setTimeout(pollRFID, 1000);
                    }
                })
                .catch(() => setTimeout(pollRFID, 1000));
        }

<!-- add this part where the logic for the confirmation fo the producted added to the database is 
 this clears the file holding the scanned data
 -->

 $result = oci_execute($stmt);
            if ($result) {
                move_uploaded_file($image_tmp_name, $image_folder);
                $success_msg[] = 'Product added!';
                
                add this part inside the if logic after this-->
        // Clear the RFID scan JSON file
                $rfid_data_path = 'rfid_scan.json'; // Adjust path if needed
                file_put_contents($rfid_data_path, json_encode(["data" => null, "scanned_at" => null])); 
                


<!-- ============================================================================================ -->
                let scanning = false;

        document.getElementById('enable-rfid').addEventListener('click', () => {
        scanning = true;
        document.getElementById('scan-status').style.display = 'block';
        document.getElementById('scan-status').textContent = "🔄 Scanning RFID...";

        
        // Trigger Python script via PHP backend
        fetch('trigger_rfid.php')
            .then(() => pollRFID());
        });

        function pollRFID() {
            if (!scanning) return;

            fetch('rfid_scan.json?' + new Date().getTime())
                .then(res => res.json())
                .then(data => {
                    if (data && data.data && data.data.product_name) {
                     // Fill the form
                        document.querySelector('[name="product_name"]').value = data.data.product_name;
                        document.querySelector('[name="description"]').value = data.data.description;
                        document.querySelector('[name="price"]').value = data.data.price;
                        document.querySelector('[name="stock"]').value = data.data.stock;
                        document.querySelector('[name="shop_id"]').value = data.data.shop_id;

                        document.getElementById('scan-status').textContent = "✔️ RFID scanned successfully!";
                        scanning = false;
                    } else {
                        setTimeout(pollRFID, 1000);
                    }
                })
                .catch(() => setTimeout(pollRFID, 1000));
        }