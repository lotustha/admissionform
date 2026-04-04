document.addEventListener('DOMContentLoaded', () => {
    // 1. Wizard Navigation
    const steps = [
        document.getElementById('step-1'),
        document.getElementById('step-2'),
        document.getElementById('step-3'),
        document.getElementById('step-4')
    ];
    const indLines = [
        document.getElementById('line-1'),
        document.getElementById('line-2'),
        document.getElementById('line-3')
    ];
    const indCircles = [
        document.getElementById('ind-1'),
        document.getElementById('ind-2'),
        document.getElementById('ind-3'),
        document.getElementById('ind-4')
    ];
    
    let currentStep = 0;
    const btnNext = document.getElementById('btn-next');
    const btnPrev = document.getElementById('btn-prev');
    const btnSubmit = document.getElementById('btn-submit');
    const form = document.getElementById('admissionForm');
    
    // Form Type Listeners
    const formTypeRadios = document.querySelectorAll('input[name="form_type_select"]');
    const admissionOnlyElements = document.querySelectorAll('.admission-only');
    const admissionOnlySteps = document.querySelectorAll('.admission-only-step');
    const formContentWrapper = document.getElementById('form_content_wrapper');
    const hiddenFormType = document.getElementById('form_type');
    let maxSteps = 4;
    let isEditingInquiry = false;

    function applyFormType() {
        let type = document.querySelector('input[name="form_type_select"]:checked')?.value;
        
        // Auto-open logic if only one type is open and no selection mechanism exists
        if (!type && window.APP_DATA && window.APP_DATA.auto_open_type) {
            type = window.APP_DATA.auto_open_type;
        }

        if (!type) return; // Keep form hidden if nothing selected
        
        hiddenFormType.value = type;
        
        // Highlight active card if it exists
        document.querySelectorAll('.form-type-card').forEach(card => card.classList.remove('selected'));
        const activeRadio = document.querySelector('input[name="form_type_select"]:checked');
        if (activeRadio) {
            activeRadio.nextElementSibling.classList.add('selected');
        }
        formContentWrapper.classList.remove('hidden', 'opacity-0');
        if (type === 'Inquiry') {
            maxSteps = 3;
            isEditingInquiry = true;
            admissionOnlyElements.forEach(el => el.classList.add('hidden'));
            admissionOnlySteps.forEach(el => el.classList.add('invisible'));
            
            // remove required from admission only fields
            document.getElementById('previous_school_name').removeAttribute('required');
            document.getElementById('gpa_or_percentage').removeAttribute('required');
            document.getElementById('schedule_id').removeAttribute('required');
            
            // Adjust placeholders
            document.getElementById('student_email').placeholder = "Optional for quick inquiry";
            document.getElementById('father_contact').placeholder = "Primary Contact No. *";
            
            // if we are past max steps, go back
            if(currentStep >= maxSteps) {
                currentStep = maxSteps - 1;
            }
        } else {
            maxSteps = 4;
            isEditingInquiry = false;
            admissionOnlyElements.forEach(el => el.classList.remove('hidden'));
            admissionOnlySteps.forEach(el => el.classList.remove('invisible'));
            
            // restore required
            document.getElementById('previous_school_name').setAttribute('required', 'required');
            document.getElementById('gpa_or_percentage').setAttribute('required', 'required');
            document.getElementById('schedule_id').setAttribute('required', 'required');
            
            // Adjust placeholders
            document.getElementById('student_email').placeholder = "To receive your digital admit card";
            document.getElementById('father_contact').placeholder = "Father's Contact No. *";
        }
        updateUI();
    }

    formTypeRadios.forEach(radio => radio.addEventListener('change', () => {
        applyFormType();
        collapseFormTypeSelector();
    }));
    
    // Re-run safely on startup to enforce state — auto-selects if only one type available
    applyFormType();
    
    // If a type was already selected (e.g., auto-open or localStorage restore), collapse the selector
    const alreadySelected = document.querySelector('input[name="form_type_select"]:checked')?.value || (window.APP_DATA && window.APP_DATA.auto_open_type);
    if (alreadySelected) {
        collapseFormTypeSelector();
    }

    function collapseFormTypeSelector() {
        const selector = document.getElementById('form_type_selection');
        const indicator = document.getElementById('form_type_indicator');
        if (!selector && !indicator) return; // Only one type available, no selector exists
        
        const type = hiddenFormType.value || 'Admission';
        const isAdmission = type === 'Admission';
        
        if (selector) {
            selector.style.maxHeight = selector.scrollHeight + 'px';
            selector.style.overflow = 'hidden';
            selector.style.transition = 'max-height 0.4s ease, opacity 0.3s ease, padding 0.4s ease';
            requestAnimationFrame(() => {
                selector.style.maxHeight = '0';
                selector.style.opacity = '0';
                selector.style.paddingTop = '0';
                selector.style.paddingBottom = '0';
            });
        }
        
        if (indicator) {
            indicator.innerHTML = `
                <div class="flex items-center justify-between px-6 py-3 bg-gradient-to-r ${isAdmission ? 'from-emerald-50 to-teal-50 border-emerald-200' : 'from-indigo-50 to-purple-50 border-indigo-200'} border-b">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center ${isAdmission ? 'bg-emerald-600' : 'bg-indigo-600'} text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isAdmission ? 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' : 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'}"></path></svg>
                        </div>
                        <span class="text-sm font-bold ${isAdmission ? 'text-emerald-800' : 'text-indigo-800'}">${isAdmission ? 'Full Admission Form' : 'Quick Inquiry Form'}</span>
                    </div>
                    <button type="button" id="changeFormTypeBtn" class="text-xs font-semibold ${isAdmission ? 'text-emerald-600 hover:text-emerald-800 hover:bg-emerald-100' : 'text-indigo-600 hover:text-indigo-800 hover:bg-indigo-100'} px-3 py-1.5 rounded-lg transition-colors">Change</button>
                </div>`;
            indicator.classList.remove('hidden');
            
            // Attach change button listener
            document.getElementById('changeFormTypeBtn')?.addEventListener('click', expandFormTypeSelector);
        }
    }

    function expandFormTypeSelector() {
        const selector = document.getElementById('form_type_selection');
        const indicator = document.getElementById('form_type_indicator');
        
        if (indicator) {
            indicator.classList.add('hidden');
            indicator.innerHTML = '';
        }
        
        if (selector) {
            selector.style.maxHeight = '';
            selector.style.opacity = '1';
            selector.style.paddingTop = '';
            selector.style.paddingBottom = '';
            selector.style.overflow = '';
        }
    }

    function updateUI() {
        steps.forEach((step, idx) => {
            if (idx === currentStep) {
                step.classList.remove('hidden-step');
            } else {
                step.classList.add('hidden-step');
            }
        });

        // Top indicators
        indCircles.forEach((circle, idx) => {
            if (idx <= currentStep) {
                circle.classList.remove('bg-gray-200', 'text-gray-600');
                circle.classList.add('bg-emerald-600', 'text-white');
            } else {
                circle.classList.add('bg-gray-200', 'text-gray-600');
                circle.classList.remove('bg-emerald-600', 'text-white');
            }
        });

        indLines.forEach((line, idx) => {
            if (idx < currentStep) {
                line.style.width = '100%';
            } else {
                line.style.width = '0%';
            }
        });

        // Buttons
        if (currentStep === 0) {
            btnPrev.classList.add('hidden');
        } else {
            btnPrev.classList.remove('hidden');
        }

        if (currentStep === maxSteps - 1) {
            btnNext.classList.add('hidden');
            btnSubmit.classList.remove('hidden');
        } else {
            btnNext.classList.remove('hidden');
            btnSubmit.classList.add('hidden');
            if (currentStep === 0) btnNext.textContent = "Continue to Parents";
            else if (currentStep === 1) btnNext.textContent = "Continue to Address";
            else if (currentStep === 2) btnNext.textContent = "Continue to Docs";
        }
    }

    function validateStep() {
        const inputs = steps[currentStep].querySelectorAll('[required]');
        for (let input of inputs) {
            if (!input.value && !input.disabled) {
                input.reportValidity();
                let fieldName = input.previousElementSibling ? input.previousElementSibling.textContent.replace('*', '').trim() : "A required field";
                alert("Missing required field: " + fieldName);
                return false;
            }
            if (input.type === 'checkbox' && !input.checked) {
                input.reportValidity();
                alert("Please agree to the mandatory declaration checkbox.");
                return false;
            }
        }
        return true;
    }

    btnNext.addEventListener('click', () => {
        if (validateStep()) {
            saveState();
            currentStep++;
            updateUI();
            window.scrollTo(0, 0);
        }
    });

    btnPrev.addEventListener('click', () => {
        saveState();
        currentStep--;
        updateUI();
        window.scrollTo(0, 0);
    });

    // 2. Class Logic Rules
    const classSelect = document.getElementById('applied_class');
    const class9Section = document.getElementById('class_9_section');
    const class11Section = document.getElementById('class_11_section');
    const seeSymbolContainer = document.getElementById('see_symbol_container');
    
    // Class 9 Opt 1 & 2
    const opt1 = document.getElementById('optional_subject_1');
    const opt2 = document.getElementById('optional_subject_2');

    // Class 11 details
    const facultySelect = document.getElementById('faculty_id');
    const facultySubjectsSection = document.getElementById('faculty_subjects_section');
    const facultyOptionalSubject = document.getElementById('faculty_optional_subject');
    const entranceSlotSection = document.getElementById('entrance_slot_section');
    const scheduleInput = document.getElementById('schedule_id');
    const chipsContainer = document.getElementById('schedule_chips_container');

    const classContactCard = document.getElementById('class-contact-card');
    const classContactName = document.getElementById('class-contact-name');
    const classContactWA = document.getElementById('class-contact-wa');

    classSelect.addEventListener('change', () => {
        const val = classSelect.value;
        class9Section.classList.add('hidden');
        class9Section.classList.remove('grid');
        class11Section.classList.add('hidden');
        if (seeSymbolContainer) seeSymbolContainer.classList.add('hidden');
        
        opt1.removeAttribute('required');
        opt2.removeAttribute('required');
        facultySelect.removeAttribute('required');
        scheduleInput.removeAttribute('required');

        // Check for Class Contact Info
        if (classContactCard) {
            classContactCard.classList.add('hidden');
            if (val && window.APP_DATA && window.APP_DATA.open_classes) {
                const classData = window.APP_DATA.open_classes.find(c => c.class_name === val);
                if (classData && classData.contact_person) {
                    classContactName.textContent = classData.contact_person;
                    if (classData.whatsapp_number) {
                        classContactWA.href = 'https://wa.me/' + classData.whatsapp_number.replace(/\D/g, '');
                        classContactWA.classList.remove('hidden');
                    } else {
                        classContactWA.classList.add('hidden');
                    }
                    classContactCard.classList.remove('hidden');
                }
            }
        }

        if (val === 'Class 9') {
            class9Section.classList.remove('hidden');
            class9Section.classList.add('grid');
            opt1.setAttribute('required', 'required');
            opt2.setAttribute('required', 'required');
        } else if (val === 'Class 11') {
            class11Section.classList.remove('hidden');
            if (seeSymbolContainer) seeSymbolContainer.classList.remove('hidden');
            facultySelect.setAttribute('required', 'required');
            populateFaculties();
        }
        
        checkEntranceSlots();
    });

    opt1.addEventListener('change', () => {
        if (opt1.value === 'Economics') {
            opt2.value = 'Account';
            opt2.style.pointerEvents = 'none';
            opt2.style.opacity = '0.6';
        } else {
            opt2.style.pointerEvents = 'auto';
            opt2.style.opacity = '1';
            opt2.value = '';
        }
        saveState();
    });

    function populateFaculties() {
        facultySelect.innerHTML = '<option value="">Select Faculty</option>';
        if (window.APP_DATA && window.APP_DATA.faculties) {
            window.APP_DATA.faculties.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id;
                opt.textContent = f.faculty_name;
                opt.dataset.requires_entrance = f.requires_entrance;
                facultySelect.appendChild(opt);
            });
        }
    }

    function checkEntranceSlots() {
        const cls = classSelect.value;
        const facId = facultySelect.value;
        
        entranceSlotSection.classList.add('hidden');
        scheduleInput.removeAttribute('required');
        chipsContainer.innerHTML = '';
        
        if (!cls) return;
        
        let slots = [];
        if (window.APP_DATA && window.APP_DATA.schedules) {
            slots = window.APP_DATA.schedules.filter(s => {
                if (s.class_name !== cls) return false;
                if (s.faculty_id && s.faculty_id != facId) return false;
                if (s.faculty_id && !facId) return false; 
                return true;
            });
        }
        
        if (slots.length > 0) {
            entranceSlotSection.classList.remove('hidden');
            scheduleInput.setAttribute('required', 'required');
            
            let validFound = slots.find(s => s.id == scheduleInput.value);
            if(!validFound) scheduleInput.value = '';
            
            slots.forEach(s => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'flex flex-col text-left p-3 rounded-lg border border-red-200 bg-white hover:border-red-400 focus:outline-none transition-all shadow-sm';
                
                const timeStr = `${s.exam_date} at ${s.exam_time.substring(0,5)}`;
                const venueStr = `${s.venue}`;
                const seatsStr = `${s.available_seats} seats left`;
                
                btn.innerHTML = `
                    <span class="font-bold text-gray-800 text-[13px] mb-1">${timeStr}</span>
                    <span class="text-xs text-gray-600 truncate">${venueStr} &bull; <span class="text-red-600 font-semibold">${seatsStr}</span></span>
                `;
                
                const highlight = () => {
                    Array.from(chipsContainer.children).forEach(cb => {
                        cb.classList.remove('ring-2', 'ring-red-500', 'border-red-500', 'bg-red-50');
                        cb.classList.add('border-red-200', 'bg-white');
                    });
                    btn.classList.add('ring-2', 'ring-red-500', 'border-red-500', 'bg-red-50');
                    btn.classList.remove('border-red-200', 'bg-white');
                };
                
                btn.addEventListener('click', () => {
                    if (scheduleInput.value == s.id) {
                        scheduleInput.value = '';
                        btn.classList.remove('ring-2', 'ring-red-500', 'border-red-500', 'bg-red-50');
                        btn.classList.add('border-red-200', 'bg-white');
                    } else {
                        scheduleInput.value = s.id;
                        highlight();
                    }
                    saveState();
                });
                
                if (scheduleInput.value == s.id) {
                    highlight();
                }
                
                chipsContainer.appendChild(btn);
            });

            // Auto-select if there is only one slot
            if (slots.length === 1) {
                scheduleInput.value = slots[0].id;
                const onlyBtn = chipsContainer.children[0];
                if (onlyBtn) {
                    onlyBtn.classList.add('ring-2', 'ring-red-500', 'border-red-500', 'bg-red-50');
                    onlyBtn.classList.remove('border-red-200', 'bg-white');
                }
                saveState();
            }
        }
    }

    facultySelect.addEventListener('change', () => {
        const facId = facultySelect.value;
        facultySubjectsSection.classList.add('hidden');

        // Hide incharge card
        const inchargeCard = document.getElementById('incharge-card');
        if (inchargeCard) inchargeCard.classList.add('hidden');
        
        if (!facId) {
            checkEntranceSlots();
            return;
        }

        // Fetch incharge info
        fetch('get_incharge.php?faculty_id=' + facId)
            .then(r => r.json())
            .then(data => {
                if (data.success && inchargeCard) {
                    document.getElementById('incharge-name').textContent = data.incharge_name;
                    document.getElementById('incharge-title').textContent = data.incharge_title || 'Class Incharge';
                    if (data.photo) {
                        const img = document.getElementById('incharge-photo');
                        img.src = data.photo;
                        document.getElementById('incharge-photo-wrap').classList.remove('hidden');
                    } else {
                        document.getElementById('incharge-photo-wrap').classList.add('hidden');
                    }
                    const waBtn = document.getElementById('incharge-wa-btn');
                    if (data.whatsapp) {
                        waBtn.href = 'https://wa.me/' + data.whatsapp.replace(/\D/g, '');
                        waBtn.classList.remove('hidden');
                    } else {
                        waBtn.classList.add('hidden');
                    }
                    inchargeCard.classList.remove('hidden');
                }
            }).catch(() => {});


        const faculty = window.APP_DATA.faculties.find(f => f.id == facId);
        if (faculty && faculty.subjects && faculty.subjects.length > 0) {
            facultyOptionalSubject.innerHTML = '<option value="">Select Subject</option>';
            faculty.subjects.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.subject_name;
                opt.textContent = sub.subject_name;
                facultyOptionalSubject.appendChild(opt);
            });
            facultySubjectsSection.classList.remove('hidden');
        }

        checkEntranceSlots();
        saveState();
    });

    // 3. Date Masking
    const dobBs = document.getElementById('dob_bs');
    dobBs.addEventListener('input', (e) => {
        let val = e.target.value.replace(/\D/g, ''); 
        if (val.length > 8) val = val.substring(0, 8);
        
        if (val.length >= 6) {
            val = val.substring(0, 4) + '-' + val.substring(4, 6) + '-' + val.substring(6, 8);
        } else if (val.length >= 4) {
            val = val.substring(0, 4) + '-' + val.substring(4, 6);
        }
        e.target.value = val;
    });

    // 4. Geographic Dropdowns
    const provSelect = document.getElementById('address_province');
    const distSelect = document.getElementById('address_district');
    const munSelect = document.getElementById('address_municipality');
    let geoData = {};

    fetch('assets/js/nepal_locations.json')
        .then(res => res.json())
        .then(data => {
            geoData = data;
            Object.keys(data).forEach(prov => {
                const opt = document.createElement('option');
                opt.value = prov;
                opt.textContent = prov;
                provSelect.appendChild(opt);
            });
            restoreState(); // Restore state after geo data is loaded
            // Re-apply form type after restore (in case auto_open_type was set)
            applyFormType();
        });

    provSelect.addEventListener('change', () => {
        const prov = provSelect.value;
        distSelect.innerHTML = '<option value="">Select District</option>';
        munSelect.innerHTML = '<option value="">Select Municipality</option>';
        munSelect.disabled = true;
        
        if (prov && geoData[prov]) {
            distSelect.disabled = false;
            Object.keys(geoData[prov]).forEach(dist => {
                const opt = document.createElement('option');
                opt.value = dist;
                opt.textContent = dist;
                distSelect.appendChild(opt);
            });
        } else {
            distSelect.disabled = true;
        }
        saveState();
    });

    distSelect.addEventListener('change', () => {
        const prov = provSelect.value;
        const dist = distSelect.value;
        munSelect.innerHTML = '<option value="">Select Municipality</option>';
        
        if (prov && dist && geoData[prov] && geoData[prov][dist]) {
            munSelect.disabled = false;
            geoData[prov][dist].forEach(mun => {
                const opt = document.createElement('option');
                opt.value = mun;
                opt.textContent = mun;
                munSelect.appendChild(opt);
            });
        } else {
            munSelect.disabled = true;
        }
        saveState();
    });

    // 5. OCR Auto-fill
    const aiBtn = document.getElementById('ai-autofill-btn');
    const marksheetInput = document.getElementById('marksheet_doc');
    const birthCertInput = document.getElementById('birth_cert');
    const aiStatus = document.getElementById('ai-status');

    if (aiBtn) {
        aiBtn.addEventListener('click', () => {
            if ((!marksheetInput.files || marksheetInput.files.length === 0) && (!birthCertInput.files || birthCertInput.files.length === 0)) {
                alert("Please select at least one document (Marksheet or Birth Certificate) to analyze.");
                return;
            }

            const formData = new FormData();
            if (marksheetInput.files && marksheetInput.files[0]) formData.append('marksheet_doc', marksheetInput.files[0]);
            if (birthCertInput.files && birthCertInput.files[0]) formData.append('birth_cert', birthCertInput.files[0]);

            aiStatus.textContent = "Analyzing documents with AI... Please wait.";
            aiStatus.className = "text-[11px] text-indigo-700 mt-2 font-medium animate-pulse";
            
            fetch('ocr_endpoint.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.extracted) {
                    aiStatus.textContent = "Success! Extracted details pre-filled.";
                    aiStatus.className = "text-[11px] text-emerald-600 mt-2 font-bold";
                    
                    const ext = data.extracted;
                    if(ext.student_first_name) document.getElementById('student_first_name').value = ext.student_first_name;
                    if(ext.student_last_name) document.getElementById('student_last_name').value = ext.student_last_name;
                    if(ext.dob_bs) document.getElementById('dob_bs').value = ext.dob_bs;
                    if(ext.dob_ad) document.getElementById('dob_ad').value = ext.dob_ad;
                    
                    if(ext.gender) {
                        const genderRadios = document.querySelectorAll('input[name="gender"]');
                        genderRadios.forEach(r => {
                            if (r.value.toLowerCase() === ext.gender.toLowerCase()) r.checked = true;
                        });
                    }
                    
                    if(ext.father_name) document.getElementById('father_name').value = ext.father_name;
                    if(ext.mother_name) document.getElementById('mother_name').value = ext.mother_name;
                    
                    if(ext.address_province) {
                        provSelect.value = ext.address_province;
                        provSelect.dispatchEvent(new Event('change'));
                        setTimeout(() => {
                            if(ext.address_district) {
                                distSelect.value = ext.address_district;
                                distSelect.dispatchEvent(new Event('change'));
                                setTimeout(() => {
                                    if(ext.address_municipality) munSelect.value = ext.address_municipality;
                                }, 300);
                            }
                        }, 300);
                    }
                    if(ext.address_ward_village) document.getElementById('address_ward_village').value = ext.address_ward_village;
                    
                    if(ext.previous_school_name) document.getElementById('previous_school_name').value = ext.previous_school_name;
                    if(ext.see_symbol_no) document.getElementById('see_symbol_no').value = ext.see_symbol_no;
                    if(ext.gpa) document.getElementById('gpa_or_percentage').value = ext.gpa;
                    
                    if(ext.current_class) {
                        let currentClassStr = String(ext.current_class).match(/\d+/);
                        if(currentClassStr && currentClassStr[0]) {
                            let nextClassNum = parseInt(currentClassStr[0]) + 1;
                            let nextClassVal = "Class " + nextClassNum;
                            let classSelectElem = document.getElementById('applied_class');
                            let optionsArray = Array.from(classSelectElem.options).map(o => o.value);
                            
                            if(optionsArray.includes(nextClassVal)) {
                                classSelectElem.value = nextClassVal;
                                classSelectElem.dispatchEvent(new Event('change'));
                            } else if (nextClassNum === 11) {
                                classSelectElem.value = "Class 11";
                                classSelectElem.dispatchEvent(new Event('change'));
                            }
                        }
                    }
                    
                    saveState();
                } else {
                    throw new Error(data.message || "Failed to extract");
                }
            })
            .catch(err => {
                console.error(err);
                aiStatus.textContent = "AI extraction failed. Please enter manually.";
                aiStatus.className = "text-[11px] text-red-600 mt-2 font-medium";
            });
        });
    }

    // 6. State Management (localStorage)
    let isRestoring = false;
    
    function saveState() {
        if (isRestoring) return;
        const formData = new FormData(form);
        const dataObj = {};
        for (let [key, value] of formData.entries()) {
            if (key !== 'pp_photo' && key !== 'marksheet_doc' && key !== 'birth_cert') {
                dataObj[key] = value;
            }
        }
        localStorage.setItem('admissionFormState', JSON.stringify(dataObj));
        localStorage.setItem('admissionCurrentStep', currentStep);
    }

    function restoreState() {
        isRestoring = true;
        const saved = localStorage.getItem('admissionFormState');
        const savedStep = localStorage.getItem('admissionCurrentStep');
        
        if (saved) {
            const dataObj = JSON.parse(saved);
            
            // Restore class first
             if(dataObj['applied_class']) {
                classSelect.value = dataObj['applied_class'];
                classSelect.dispatchEvent(new Event('change'));
            }
            // Restore faculty if 11
            if(dataObj['faculty_id']) {
                facultySelect.value = dataObj['faculty_id'];
                facultySelect.dispatchEvent(new Event('change'));
            }

            // Restore Province
             if(dataObj['address_province']) {
                 provSelect.value = dataObj['address_province'];
                 provSelect.dispatchEvent(new Event('change'));
             }
             // Restore District
             if(dataObj['address_district']) {
                 distSelect.value = dataObj['address_district'];
                 distSelect.dispatchEvent(new Event('change'));
             }

            Object.keys(dataObj).forEach(key => {
                const el = document.getElementById(key) || document.querySelector(`[name="${key}"]`);
                if (el && el.type !== 'file' && el.type !== 'checkbox') {
                    el.value = dataObj[key];
                } else if (el && el.type === 'checkbox') {
                    el.checked = (dataObj[key] === '1' || dataObj[key] === 'on' || dataObj[key] === true);
                }
            });
            
            // Execute dependent logic
            if(dataObj['optional_subject_1']) opt1.dispatchEvent(new Event('change'));
        }
        
        if (savedStep) {
            currentStep = parseInt(savedStep, 10);
            updateUI();
        }
        isRestoring = false;
    }

    form.addEventListener('input', saveState);
    form.addEventListener('change', saveState);
    
    // Normal submit clear just in case
    form.addEventListener('submit', () => {
        localStorage.removeItem('admissionFormState');
        localStorage.removeItem('admissionCurrentStep');
    });

    // 7. Profile Photo Preview & Cropper
    const ppPhotoInput = document.getElementById('pp_photo');
    const photoPreview = document.getElementById('photo_preview');
    const cropperModal = document.getElementById('cropper-modal');
    const cropperImage = document.getElementById('cropper-image');
    let cropperInstance = null;
    
    if (ppPhotoInput && photoPreview && cropperModal) {
        ppPhotoInput.addEventListener('change', function(e) {
            const file = this.files && this.files[0];
            // Only proc cropper if it's an actual file selection and not our simulated cropped blob injection
            if (file && file.name !== 'cropped_passport.jpg') {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    cropperImage.src = evt.target.result;
                    cropperModal.classList.remove('hidden');
                    
                    if (cropperInstance) cropperInstance.destroy();
                    
                    cropperInstance = new Cropper(cropperImage, {
                        aspectRatio: 1, // Passport photo square
                        viewMode: 1,
                        autoCropArea: 1,
                    });
                };
                reader.readAsDataURL(file);
            } else if (!file) {
                photoPreview.setAttribute('src', '');
                photoPreview.classList.add('hidden');
            }
        });

        document.getElementById('btn-cancel-crop').addEventListener('click', () => {
            cropperModal.classList.add('hidden');
            ppPhotoInput.value = ''; // Reset input
            photoPreview.classList.add('hidden');
            if (cropperInstance) {
                cropperInstance.destroy();
                cropperInstance = null;
            }
        });
        
        document.getElementById('btn-save-crop').addEventListener('click', () => {
            if (cropperInstance) {
                cropperInstance.getCroppedCanvas({
                    width: 300,
                    height: 300,
                    fillColor: '#fff',
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                }).toBlob((blob) => {
                    const dt = new DataTransfer();
                    dt.items.add(new File([blob], 'cropped_passport.jpg', { type: 'image/jpeg' }));
                    ppPhotoInput.files = dt.files;
                    
                    const url = URL.createObjectURL(blob);
                    photoPreview.setAttribute('src', url);
                    photoPreview.classList.remove('hidden');
                    
                    cropperModal.classList.add('hidden');
                    cropperInstance.destroy();
                    cropperInstance = null;
                }, 'image/jpeg', 0.9);
            }
        });
    }

    // 8. Prevent submission blocking by hidden inputs & Verify Global File
    btnSubmit.addEventListener('click', (e) => {
        e.preventDefault(); // Intercept the click
        // (Passport photo is no longer required globally)

        if (validateStep()) {
            btnSubmit.disabled = true;
            btnSubmit.classList.add('opacity-75', 'cursor-not-allowed');
            const submitText = document.getElementById('btn-submit-text');
            const submitSpinner = document.getElementById('btn-submit-spinner');
            if (submitText) submitText.textContent = 'Submitting...';
            if (submitSpinner) submitSpinner.classList.remove('hidden');

            localStorage.removeItem('admissionFormState');
            localStorage.removeItem('admissionCurrentStep');
            form.submit();
        }
    });

    // Initial update
    updateUI();
});
