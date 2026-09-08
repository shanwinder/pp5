document.addEventListener("DOMContentLoaded", function() {
    // --- 1. Grade Calculation Logic ---
    const calculateGrade = (score) => {
        if (score >= 80) return { grade: "4", class: "bg-excellent" };
        if (score >= 75) return { grade: "3.5", class: "bg-excellent" };
        if (score >= 70) return { grade: "3", class: "bg-good" };
        if (score >= 65) return { grade: "2.5", class: "bg-good" };
        if (score >= 60) return { grade: "2", class: "bg-pass" };
        if (score >= 55) return { grade: "1.5", class: "bg-pass" };
        if (score >= 50) return { grade: "1", class: "bg-pass" };
        if (score > 0) return { grade: "0", class: "bg-fail" }; 
        return { grade: "-", class: "text-muted" }; 
    };

    const updateRowTotals = () => {
        const rows = document.querySelectorAll('.student-row');
        
        rows.forEach(row => {
            let total = 0;
            const inputs = row.querySelectorAll('.score-input');
            
            inputs.forEach(input => {
                const val = parseFloat(input.value) || 0;
                total += val;
            });
            
            const totalScoreCell = row.querySelector('.total-score');
            totalScoreCell.textContent = Number.isInteger(total) ? total : total.toFixed(1);
            
            if (total > 0) {
                totalScoreCell.style.color = '#374151';
            } else {
                totalScoreCell.style.color = '#9ca3af';
            }
            
            const gradeCell = row.querySelector('.final-grade');
            const result = calculateGrade(total);
            gradeCell.textContent = result.grade;
            gradeCell.className = "text-center grade-col final-grade rounded-end " + result.class;
        });
    };

    updateRowTotals();

    // --- 2. Excel-like Drag Selection & Copy-Paste Logic ---
    
    // Map grid to arrays for easy relative calculation
    const inputsGrid = [];
    document.querySelectorAll('.student-row').forEach((row, rowIndex) => {
        const rowInputs = row.querySelectorAll('.score-input');
        const rowArr = [];
        rowInputs.forEach((input, colIndex) => {
            input.dataset.row = rowIndex;
            input.dataset.col = colIndex;
            // Prevent native text dragging across boundaries if possible by tracking mouse
            input.addEventListener('dragstart', (e) => e.preventDefault());
            rowArr.push(input);
        });
        inputsGrid.push(rowArr);
    });

    let isDragging = false;
    let startRow = -1;
    let startCol = -1;
    let selectedCells = new Set();
    let currentFocusCell = null;

    const clearSelection = () => {
        selectedCells.forEach(cell => {
            cell.classList.remove('selected-cell');
        });
        selectedCells.clear();
    };

    const selectRange = (r1, c1, r2, c2) => {
        clearSelection();
        const minR = Math.min(r1, r2);
        const maxR = Math.max(r1, r2);
        const minC = Math.min(c1, c2);
        const maxC = Math.max(c1, c2);
        
        for (let r = minR; r <= maxR; r++) {
            for (let c = minC; c <= maxC; c++) {
                if (inputsGrid[r] && inputsGrid[r][c]) {
                    const cell = inputsGrid[r][c];
                    cell.classList.add('selected-cell');
                    selectedCells.add(cell);
                }
            }
        }
    };

    // Global mouse listeners for the drag box
    document.addEventListener('mousedown', (e) => {
        if (e.target.classList.contains('score-input')) {
            isDragging = true;
            startRow = parseInt(e.target.dataset.row);
            startCol = parseInt(e.target.dataset.col);
            selectRange(startRow, startCol, startRow, startCol);
            currentFocusCell = e.target;
        } else {
            clearSelection();
            currentFocusCell = null;
        }
    });

    document.addEventListener('mouseover', (e) => {
        if (isDragging && e.target.classList.contains('score-input')) {
            const currentRow = parseInt(e.target.dataset.row);
            const currentCol = parseInt(e.target.dataset.col);
            
            // if selecting multiple, blur to prevent weird caret behavior
            if (currentRow !== startRow || currentCol !== startCol) {
                if (document.activeElement) document.activeElement.blur(); 
            }
            selectRange(startRow, startCol, currentRow, currentCol);
        }
    });

    document.addEventListener('mouseup', () => {
        isDragging = false;
        // Optionally focus the start cell so paste acts relative to it
        if (currentFocusCell && selectedCells.size === 1) {
            currentFocusCell.focus();
        }
    });

    // Handle Input validation and auto row calculation
    const attachInputEvents = () => {
        const scoreInputs = document.querySelectorAll('.score-input');
        scoreInputs.forEach(input => {
            input.addEventListener('input', function() {
                let maxVal = parseFloat(this.getAttribute('max'));
                let currentVal = parseFloat(this.value);
                
                if (currentVal > maxVal) this.value = maxVal;
                if (currentVal < 0) this.value = 0;
                
                updateRowTotals();
            });

            input.addEventListener('focus', function() {
                if(selectedCells.size <= 1) {
                    this.select();
                    currentFocusCell = this;
                }
            });
            
            // Arrow Keys Navigation (Excel feel)
            input.addEventListener('keydown', function(e) {
                const currentTabIndex = parseInt(this.getAttribute('tabindex'));
                const inputsArray = Array.from(document.querySelectorAll('.score-input'));
                
                // Assuming roughly 4 inputs per row
                const colsPerRow = inputsGrid[0] ? inputsGrid[0].length : 4; 

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    clearSelection();
                    const nextInput = inputsArray.find(i => parseInt(i.getAttribute('tabindex')) === currentTabIndex + colsPerRow);
                    if (nextInput) nextInput.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    clearSelection();
                    const prevInput = inputsArray.find(i => parseInt(i.getAttribute('tabindex')) === currentTabIndex - colsPerRow);
                    if (prevInput) prevInput.focus();
                } else if (e.key === 'ArrowRight' && this.selectionStart === this.value.length) {
                     // Native arrow right moves caret, but if at end, jump cell
                     e.preventDefault();
                     clearSelection();
                     const nextInput = inputsArray.find(i => parseInt(i.getAttribute('tabindex')) === currentTabIndex + 1);
                     if (nextInput) nextInput.focus();
                } else if (e.key === 'ArrowLeft' && this.selectionStart === 0) {
                     e.preventDefault();
                     clearSelection();
                     const prevInput = inputsArray.find(i => parseInt(i.getAttribute('tabindex')) === currentTabIndex - 1);
                     if (prevInput) prevInput.focus();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                     clearSelection();
                     const nextInput = inputsArray.find(i => parseInt(i.getAttribute('tabindex')) === currentTabIndex + 1);
                     if (nextInput) nextInput.focus();
                }
            });
        });
    };
    attachInputEvents();

    // --- 3. Keyboard Shortcuts (Delete, Copy, Paste) ---
    document.addEventListener('keydown', (e) => {
        // Delete / Backspace to clear cells
        if ((e.key === 'Delete' || e.key === 'Backspace') && selectedCells.size > 0 && document.activeElement.tagName !== 'INPUT') {
            e.preventDefault();
            selectedCells.forEach(cell => {
                cell.value = '';
                cell.dispatchEvent(new Event('input', { bubbles: true })); 
            });
        }
        
        // Ctrl+C / Cmd+C (Copy)
        if ((e.ctrlKey || e.metaKey) && e.key === 'c' && selectedCells.size > 0) {
            e.preventDefault();
            let minR = Infinity, maxR = -1, minC = Infinity, maxC = -1;
            selectedCells.forEach(cell => {
                const r = parseInt(cell.dataset.row);
                const c = parseInt(cell.dataset.col);
                minR = Math.min(minR, r); maxR = Math.max(maxR, r);
                minC = Math.min(minC, c); maxC = Math.max(maxC, c);
            });
            
            let copyText = '';
            for (let r = minR; r <= maxR; r++) {
                let rowText = [];
                for (let c = minC; c <= maxC; c++) {
                    if (inputsGrid[r] && inputsGrid[r][c] && selectedCells.has(inputsGrid[r][c])) {
                        rowText.push(inputsGrid[r][c].value);
                    } else {
                        rowText.push('');
                    }
                }
                copyText += rowText.join('\t') + '\n';
            }
            navigator.clipboard.writeText(copyText);
            
            // Visual feedback flash
            selectedCells.forEach(cell => {
                const oldBg = cell.style.backgroundColor;
                cell.style.backgroundColor = 'rgba(16, 185, 129, 0.4)'; // green flash
                setTimeout(() => cell.style.backgroundColor = oldBg, 150);
            });
        }
    });

    // Handing Paste (Ctrl+V) from Excel/Clipboard
    document.addEventListener('paste', (e) => {
        // Only trigger if pasting into our context
        if (e.target.classList.contains('score-input') || selectedCells.has(e.target) || currentFocusCell) {
            e.preventDefault();
            
            const pasteData = (e.clipboardData || window.clipboardData).getData('text');
            const rows = pasteData.split(/\r?\n/);
            
            // Determine starting cell
            let startR = -1, startC = -1;
            if (selectedCells.size > 0) {
                // Find top-leftmost cell from selection
                let minR = Infinity, minC = Infinity;
                selectedCells.forEach(cell => {
                    const r = parseInt(cell.dataset.row);
                    const c = parseInt(cell.dataset.col);
                    if (r < minR) { minR = r; minC = c; }
                    else if (r === minR && c < minC) { minC = c; }
                });
                startR = minR; startC = minC;
            } else if (currentFocusCell) {
                startR = parseInt(currentFocusCell.dataset.row);
                startC = parseInt(currentFocusCell.dataset.col);
            } else if (e.target.classList.contains('score-input')) {
                startR = parseInt(e.target.dataset.row);
                startC = parseInt(e.target.dataset.col);
            }

            if (startR === -1 || startC === -1) return;

            let maxR = startR, maxC = startC;
            
            rows.forEach((rowStr, rIdx) => {
                if (rowStr === undefined) return; // skip if completely empty line but handle empty cell values
                const cols = rowStr.split('\t');
                
                // If the entire row is empty string but length > 0, it means blank line in CSV/TSV, skip if it's the ending line
                if (rIdx === rows.length - 1 && rowStr.trim() === '') return;
                
                cols.forEach((colVal, cIdx) => {
                    const targetR = startR + rIdx;
                    const targetC = startC + cIdx;
                    if (inputsGrid[targetR] && inputsGrid[targetR][targetC]) {
                        const cell = inputsGrid[targetR][targetC];
                        
                        let strVal = colVal.trim();
                        if (strVal === '') {
                            cell.value = '';
                            cell.dispatchEvent(new Event('input', { bubbles: true }));
                        } else {
                            let cleanVal = parseFloat(strVal);
                            if (!isNaN(cleanVal)) {
                                let maxVal = parseFloat(cell.getAttribute('max') || '100');
                                if (cleanVal > maxVal) cleanVal = maxVal;
                                if (cleanVal < 0) cleanVal = 0;
                                cell.value = cleanVal;
                                cell.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        }
                        maxR = Math.max(maxR, targetR);
                        maxC = Math.max(maxC, targetC);
                    }
                });
            });
            
            // Highlight pasted range
            selectRange(startR, startC, maxR, maxC);
        }
    });
});
