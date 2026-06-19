// assets/js/script.js
// Client-side interactions for Roommate Expense Management System

document.addEventListener("DOMContentLoaded", function () {
    // 1. Expense Split Dynamic Calculations (add_expense.php)
    const amountInput = document.getElementById("amount");
    const splitTypeRadios = document.querySelectorAll('input[name="split_type"]');
    const equalSplitSection = document.getElementById("equal-split-section");
    const customSplitSection = document.getElementById("custom-split-section");
    const submitBtn = document.getElementById("submit-expense-btn");

    if (amountInput) {
        // Toggle Split Views
        function toggleSplitSections() {
            const selectedType = document.querySelector('input[name="split_type"]:checked').value;
            if (selectedType === "equal") {
                equalSplitSection.classList.remove("d-none");
                customSplitSection.classList.add("d-none");
                calculateEqualSplit();
            } else {
                equalSplitSection.classList.add("d-none");
                customSplitSection.classList.remove("d-none");
                validateCustomSplit();
            }
        }

        splitTypeRadios.forEach(radio => {
            radio.addEventListener("change", toggleSplitSections);
        });

        // Recalculate splits on amount change
        amountInput.addEventListener("input", function() {
            const selectedType = document.querySelector('input[name="split_type"]:checked').value;
            if (selectedType === "equal") {
                calculateEqualSplit();
            } else {
                validateCustomSplit();
            }
        });

        // Equal Split Calculation
        const memberChecks = document.querySelectorAll(".member-check");
        memberChecks.forEach(chk => {
            chk.addEventListener("change", calculateEqualSplit);
            
            // Toggle active card styling on click
            chk.addEventListener("change", function() {
                const card = this.closest(".split-member-card");
                if (this.checked) {
                    card.classList.add("selected");
                } else {
                    card.classList.remove("selected");
                }
            });
        });

        function calculateEqualSplit() {
            const totalAmount = parseFloat(amountInput.value) || 0;
            const checkedMembers = document.querySelectorAll(".member-check:checked");
            const checkedCount = checkedMembers.length;
            const splitWarning = document.getElementById("split-warning");
            
            if (checkedCount === 0) {
                if (splitWarning) {
                    splitWarning.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Select at least one roommate.</span>';
                }
                submitBtn.disabled = true;
                return;
            }

            const share = (totalAmount / checkedCount).toFixed(2);
            
            // Update individual share labels in equal splits
            memberChecks.forEach(chk => {
                const shareLabel = document.getElementById("share-amount-" + chk.value);
                if (chk.checked) {
                    shareLabel.textContent = "₹" + share;
                } else {
                    shareLabel.textContent = "₹0.00";
                }
            });

            if (splitWarning) {
                splitWarning.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Splits calculated successfully.</span>';
            }
            submitBtn.disabled = false;
        }

        // Custom Split Validation
        const customInputs = document.querySelectorAll(".custom-amount-input");
        customInputs.forEach(input => {
            input.addEventListener("input", validateCustomSplit);
        });

        function validateCustomSplit() {
            const totalAmount = parseFloat(amountInput.value) || 0;
            let sum = 0;
            
            customInputs.forEach(input => {
                sum += parseFloat(input.value) || 0;
            });
            
            sum = parseFloat(sum.toFixed(2));
            const diff = parseFloat((totalAmount - sum).toFixed(2));
            const splitWarning = document.getElementById("split-warning");

            if (!splitWarning) return;

            if (totalAmount <= 0) {
                splitWarning.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Total expense amount must be greater than 0.</span>';
                submitBtn.disabled = true;
                return;
            }

            if (diff === 0) {
                splitWarning.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Distributed amount matches total expense sum exactly!</span>';
                submitBtn.disabled = false;
            } else if (diff > 0) {
                splitWarning.innerHTML = `<span class="text-warning"><i class="fa-solid fa-spinner"></i> Undistributed amount: <strong>₹${diff.toFixed(2)}</strong> remaining.</span>`;
                submitBtn.disabled = true;
            } else {
                splitWarning.innerHTML = `<span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Exceeded total expense by: <strong>₹${Math.abs(diff).toFixed(2)}</strong>.</span>`;
                submitBtn.disabled = true;
            }
        }

        // Initialize display
        toggleSplitSections();
    }

    // 2. Alert Dismiss Auto-timer
    const alerts = document.querySelectorAll(".alert-dismissible");
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// 3. Global confirmation functions
function confirmDelete(message) {
    return confirm(message || "Are you sure you want to delete this record? This action cannot be undone.");
}
