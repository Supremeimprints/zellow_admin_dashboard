<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/giftbox_functions.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$giftBoxId = $_GET['id'] ?? null;
if (!$giftBoxId) {
    header('Location: giftbox_manager.php');
    exit();
}

$giftBox = getGiftBoxById($db, $giftBoxId);
$attributes = getGiftBoxAttributes($db, $giftBoxId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Box Attributes</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/nav/collapsed.php'; ?>
        
        <div class="container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-cog me-2"></i>
                    Customize: <?= htmlspecialchars($giftBox['name']) ?>
                </h2>
                <a href="giftbox_manager.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add Attribute</h5>
                        </div>
                        <div class="card-body">
                            <form action="giftbox_add_attribute.php" method="POST">
                                <input type="hidden" name="gift_box_id" value="<?= $giftBoxId ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Attribute Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Input Type</label>
                                    <select name="input_type" class="form-select" id="inputType" required>
                                        <option value="text">Text Input</option>
                                        <option value="dropdown">Dropdown</option>
                                        <option value="color_picker">Color Picker</option>
                                        <option value="file_upload">File Upload</option>
                                    </select>
                                </div>

                                <div id="dropdownOptions" class="mb-3" style="display: none;">
                                    <label class="form-label">Dropdown Options</label>
                                    <div id="optionsContainer">
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control" placeholder="Option value" 
                                                   name="option_values[]">
                                            <input type="number" class="form-control" placeholder="Additional price" 
                                                   name="option_prices[]" step="0.01">
                                            <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addOption()">
                                        <i class="fas fa-plus"></i> Add Option
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_required" class="form-check-input" id="isRequired">
                                        <label class="form-check-label" for="isRequired">Required Field</label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Add Attribute</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Attributes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($attributes)): ?>
                                <p class="text-muted">No attributes configured yet.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($attributes as $attr): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($attr['name']) ?></h6>
                                                    <small class="text-muted">
                                                        Type: <?= ucfirst($attr['input_type']) ?>
                                                        <?php if ($attr['is_required']): ?>
                                                            <span class="badge bg-danger ms-2">Required</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteAttribute(<?= $attr['id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('inputType').addEventListener('change', function() {
            const dropdownOptions = document.getElementById('dropdownOptions');
            dropdownOptions.style.display = this.value === 'dropdown' ? 'block' : 'none';
        });

        function addOption() {
            const container = document.getElementById('optionsContainer');
            const newOption = document.createElement('div');
            newOption.className = 'input-group mb-2';
            newOption.innerHTML = `
                <input type="text" class="form-control" placeholder="Option value" name="option_values[]">
                <input type="number" class="form-control" placeholder="Additional price" name="option_prices[]" step="0.01">
                <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(newOption);
        }

        function removeOption(button) {
            button.closest('.input-group').remove();
        }

        // Form submission handling
        document.querySelector('form').addEventListener('submit', function(e) {
            if (document.getElementById('inputType').value === 'dropdown') {
                e.preventDefault();
                const options = [];
                const values = document.getElementsByName('option_values[]');
                const prices = document.getElementsByName('option_prices[]');
                
                for (let i = 0; i < values.length; i++) {
                    if (values[i].value) {
                        options.push({
                            value: values[i].value,
                            price: prices[i].value || 0
                        });
                    }
                }
                
                const optionsInput = document.createElement('input');
                optionsInput.type = 'hidden';
                optionsInput.name = 'options';
                optionsInput.value = JSON.stringify(options);
                this.appendChild(optionsInput);
                this.submit();
            }
        });
        
        function deleteAttribute(attributeId) {
            if (confirm('Are you sure you want to delete this attribute?')) {
                window.location.href = `giftbox_delete_attribute.php?id=${attributeId}&box_id=<?= $giftBoxId ?>`;
            }
        }
    </script>
</body>
</html>
