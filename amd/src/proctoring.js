// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AutoProctor proctoring module for quiz access.
 *
 * @module     quizaccess_autoproctor/proctoring
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["jquery", "core/templates", "core/ajax"], function ($, Templates, Ajax) {
    // Constants
    const IFRAME_NAME = "ap-iframe";
    const MOODLE_PREFLIGHT_FORM_ID = "mod_quiz_preflight_form";
    const MOODLE_PAGE_CONTENT_ID = "page-content";
    const MOODLE_FINISH_FORM_SUBMIT_BTN = "#frm-finishattempt button[type='submit']";
    const MOODLE_FINISH_MODAL_SUBMIT_BTN = "div.modal-footer > button.btn.btn-primary";

    // Configuration
    const CONFIG = {
        SDK_MAX_RETRIES: 10,
        SDK_RETRY_DELAY_MS: 1000,
        SESSION_MAX_RETRIES: 5,
        SESSION_RETRY_DELAY_MS: 1000,
        ELEMENT_WAIT_INTERVAL_MS: 500,
        ELEMENT_WAIT_TIMEOUT_MS: 30000,
        LOADER_CHECK_INTERVAL_MS: 500,
        LOADER_CHECK_TIMEOUT_MS: 60000
    };

    // Cached variables
    let _apInstance;
    let _trackingOptions;
    let _testAttemptId;
    let _lookupKey;
    let _apDomain;
    let _apEnv;
    let _userDetails;

    // Flags
    let isPreflightFormSubmitted = false;
    let isApProgressCompleted = false;
    let proctoringInProgress = false;

    // Cached DOM elements
    let $apIframe;
    let $apIframeLoader;

    /**
     * Generates the credentials object required by AutoProctor.
     * The hashedTestAttemptId is computed server-side to avoid exposing the client secret.
     * @param {string} clientId
     * @param {string} hashedTestAttemptId - Pre-computed HMAC-SHA256 hash (base64 encoded)
     * @param {string} testAttemptId
     * @param {string} apDomain - The AutoProctor API domain
     * @param {string} apEnv - The environment (development/production)
     * @returns {object}
     */
    function getCredentials(clientId, hashedTestAttemptId, testAttemptId, apDomain, apEnv) {
        return {
            clientId,
            testAttemptId,
            hashedTestAttemptId,
            domain: apDomain || "https://autoproctor.co",
            environment: apEnv || "production",
        };
    }

    /**
     * Generates the report options object required by AutoProctor.
     * @param {boolean} includeSessionRecording - Whether to include session recording in the report
     * @param {{name: string, email: string}} [userDetails] - Optional user details for the report
     * @returns {object}
     */
    const getReportOptions = (includeSessionRecording = true, userDetails = null) => {
        const options = {
            showProctoringOverview: true,
            showProctoringSummary: true,
            showSessionRecording: includeSessionRecording,
            proctoringSummaryDOMId: "ap-report__proctor",
            proctoringOverviewDOMId: "ap-report__overview",
            sessionRecordingDOMId: "ap-report__session",
            groupReportsIntoTabs: false,
            showDownloadReportBtn: true,
        };

        const resolvedUserDetails = userDetails || _userDetails;
        if (resolvedUserDetails) {
            options.userDetails = resolvedUserDetails;
        }

        return options;
    };

    /**
     * Generates the proctoring options object required by AutoProctor.
     * @param {object} trackingOptions
     * @returns {object}
     */
    const getProctoringOptions = (trackingOptions) => {
        const proctoringOptions = {
            trackingOptions: trackingOptions ?? {
                audio: true,
                numHumans: true,
                tabSwitch: true,
                photosAtRandom: false,
                detectMultipleScreens: true,
                forceFullScreen: false,
                auxiliaryDevice: false,
                recordSession: true,
            },
            showHowToVideo: false,
            lookupKey: _lookupKey,
            testContainerId: MOODLE_PAGE_CONTENT_ID,
            alertBeforeUnload: false,
        };
        if (_userDetails) {
            proctoringOptions.userDetails = _userDetails;
        }
        return proctoringOptions;
    };

    /**
     * Tracks URL changes in an iframe and executes a callback when changes occur
     * @param {string} iframeId - The ID of the iframe to monitor
     * @param {Function} callback - Function to call when URL changes, receives new URL as parameter
     */
    const onIframeUrlChanged = (iframeId, callback) => {
        const $iframe = document.getElementById(iframeId);
        let previousUrl = null;

        // Main function to handle URL changes
        const handleUrlChange = () => {
            const currentUrl = $iframe.contentWindow.location.href;

            // Show loading indicator while iframe navigates
            $apIframeLoader.classList.remove("aptw-hidden");

            // Only process if URL has actually changed
            if (currentUrl !== previousUrl) {
                callback(currentUrl);
                previousUrl = currentUrl;
            }
        };

        // Set up iframe load event. handleUrlChange is called both when iframe is loaded
        // as well as when iframe is unloaded. While maintaining focus on the main window.
        $iframe.addEventListener("load", () => {
            handleUrlChange();

            // Add unload listener to iframe content
            $iframe.contentWindow.addEventListener("unload", () => {
                window.focus();
                handleUrlChange();
            });

            hideLoaderIfProgressCompleted();
        });
    };

    /**
     * Hides the loader if the progress is completed.
     * Has a timeout to prevent infinite polling.
     * @param {number} startTime - Timestamp when polling started (for timeout tracking)
     */
    const hideLoaderIfProgressCompleted = (startTime = Date.now()) => {
        if (isApProgressCompleted) {
            $apIframeLoader?.remove();
            return;
        }

        // Check for timeout
        if (Date.now() - startTime > CONFIG.LOADER_CHECK_TIMEOUT_MS) {
            console.warn(`[AP] Loader check timed out after ${CONFIG.LOADER_CHECK_TIMEOUT_MS}ms`);
            $apIframeLoader?.remove();
            return;
        }

        setTimeout(() => {
            hideLoaderIfProgressCompleted(startTime);
        }, CONFIG.LOADER_CHECK_INTERVAL_MS);
    };

    /**
     * Handles URL changes in the iframe and starts or stops the proctoring process accordingly.
     * Proctoring will only start on attempt.php and summary.php
     * And will stop on any other page
     * @param {string} newUrl - The new URL of the iframe
     */
    const handleIframeUrlChange = async (newUrl) => {
        const fileLocation = getUrlFileLocation(newUrl);

        /**
         * If it is summary.php, wait for the finish form to be submitted
         * and then wait for the final submit button to appear in the modal
         * and then stop the AP session on click of final submit button before
         * redirecting to newUrl. Or else if the AP session is not started,
         * just redirect to newUrl
         */
        if (fileLocation === "summary.php") {
            // Handle quiz submission process
            const handleFinalSubmit = ($finalSubmitBtn) => {
                $finalSubmitBtn.addEventListener("click", async (e) => {
                    if (proctoringInProgress) {
                        // Prevent Moodle's redirect until AutoProctor finishes
                        e.preventDefault();
                        e.stopPropagation();

                        await _apInstance?.stop();
                        window.addEventListener("apMonitoringStopped", () => {
                            proctoringInProgress = false;
                            // Now manually click the button to trigger Moodle's submission
                            $finalSubmitBtn.click();
                        });
                    }
                    // If not proctoring, let the default behavior proceed
                });
            };

            // Wait for finish form button and set up submission flow
            waitForElement(
                MOODLE_FINISH_FORM_SUBMIT_BTN,
                ($finishBtn) => {
                    $finishBtn.addEventListener("click", () => {
                        // Wait for final submit button to appear in the modal
                        waitForElement(MOODLE_FINISH_MODAL_SUBMIT_BTN, handleFinalSubmit, true);
                    });
                },
                true
            );
            return;
        }

        // If it is review.php and proctoring is in progress,
        // stop the AP session and redirect to newUrl
        if (fileLocation === "review.php" && proctoringInProgress) {
            await _apInstance?.stop();
            window.addEventListener("apMonitoringStopped", () => {
                proctoringInProgress = false;
                window.location.href = newUrl;
            });
            return; // Wait for apMonitoringStopped before redirecting
        }

        // Create new AP session on attempt.php
        if (fileLocation === "attempt.php") {
            await createNewApSession(newUrl, _testAttemptId, _trackingOptions);
            return;
        }

        // If it is any other page, redirect to it
        window.location.href = newUrl;
    };

    /**
     * Creates a new AP session and if it fails, it will retry up to CONFIG.SESSION_MAX_RETRIES times
     * @param {string} url
     * @param {string} testAttemptId
     * @param {object} trackingOptions
     * @param {number} retriesLeft
     */
    const createNewApSession = (url, testAttemptId, trackingOptions, retriesLeft = CONFIG.SESSION_MAX_RETRIES) => {
        const params = new URL(url).searchParams;
        const attemptId = params.get("attempt");

        if (!attemptId) {
            console.error("[AP] Missing attemptId for session creation");
            return;
        }

        Ajax.call([{
            methodname: 'quizaccess_autoproctor_create_session',
            args: {
                attemptid: parseInt(attemptId, 10),
                test_attempt_id: testAttemptId,
                tracking_options: JSON.stringify(trackingOptions),
            },
        }])[0]
            .then(data => {
                if (!data.success) {
                    console.error("[AP] Session creation failed:", data.error);
                }
            })
            .catch(error => {
                console.error("[AP] Session creation error:", error);
                if (retriesLeft > 0) {
                    console.log(`[AP] Retrying session creation (${retriesLeft} attempts left)...`);
                    setTimeout(
                        () => createNewApSession(url, testAttemptId, trackingOptions, retriesLeft - 1),
                        CONFIG.SESSION_RETRY_DELAY_MS
                    );
                } else {
                    console.error("[AP] Session creation failed after all retries");
                }
            });
    };

    /**
     * Adds an iframe to the document body with the specified attributes and styles.
     * Caches iframe in window object for later use
     */
    const addIframe = () => {
        const $iframe = document.createElement("iframe");
        $iframe.name = IFRAME_NAME;
        $iframe.id = IFRAME_NAME;
        $iframe.classList.add(
            IFRAME_NAME,
            "aptw-hidden",
            "aptw-absolute",
            "aptw-inset-0",
            "aptw-w-screen",
            "aptw-h-screen"
        );
        document.body.append($iframe);

        // Cache iframe for later use
        $apIframe = $iframe;
    };

    /**
     * Adds a page loader to the document body.
     * Caches loader in window object for later use
     */
    const addPageLoader = async () => {
        const loaderName = "ap-iframe-loader";
        $apIframeLoader = document.createElement("div");
        $apIframeLoader.id = loaderName;

        // IMPORTANT: Z-index must be greater than the z-index of the page content
        // but less than the z-index of Swals shown by AutoProctor
        $apIframeLoader.style.zIndex = 1020;

        $apIframeLoader.classList.add(
            "aptw-fixed",
            "aptw-top-0",
            "aptw-bottom-0",
            "aptw-hidden",
            "aptw-w-screen",
            "aptw-h-screen",
            "aptw-bg-white",
            "aptw-flex",
            "aptw-items-center",
            "aptw-justify-center"
        );

        document.body.append($apIframeLoader);

        try {
            const html = await Templates.render("quizaccess_autoproctor/loader", {});
            if (html) {
                $apIframeLoader.innerHTML = html;
                bindApProgressUpdate();
            }
        } catch (error) {
            // Fallback loader in case template fails
            $apIframeLoader.innerHTML = '<div class="aptw-text-center">Loading...</div>';
        }
    };

    /**
     * Binds the apProgressUpdate event to the loader to update the progress bar
     */
    const bindApProgressUpdate = () => {
        window.addEventListener("apProgressUpdate", (e) => {
            const { action } = e.detail;
            if (action === "hide") {
                isApProgressCompleted = true;
                // Don't hide loader here - let hideLoaderIfProgressCompleted handle it
                // after iframe finishes loading
            } else {
                // Show progress bar on any progress event (not just action === "show")
                document.getElementById("ap-progress-text").style.display = "block";
                document.getElementById("pre-loader-text").style.display = "none";
                $apIframeLoader?.classList.remove("aptw-hidden");
            }
            incrementSetupProgBar();
        });

        /**
         * Increments the progress bar by 10% on each call
         */
        function incrementSetupProgBar() {
            const progressBarPercent = document.getElementById("ap-progress-bar-percent");
            const progressBar = document.getElementById("ap-progress-bar");

            if (!progressBarPercent || !progressBar) {
                return;
            }

            const currentVal = parseInt(progressBarPercent.innerText) || 0;
            const newVal = currentVal + 10;

            progressBarPercent.innerText = newVal;
            progressBar.style.width = newVal + "%";
        }
    };

    /**
     * Adds an event listener to the preflight form to intercept the submit event
     * @param {object} $preflightForm
     */
    const addSubmitPreflightEvent = ($preflightForm) => {
        // Set start attempt button to the center
        const formActionsContainer = $preflightForm.getElementsByClassName("felement");
        if (formActionsContainer.length > 0) {
            const buttonContainer =
                formActionsContainer[formActionsContainer.length - 1].getElementsByClassName("aptw-flex");
            if (buttonContainer.length > 0) {
                buttonContainer[0].classList.add("aptw-justify-center");
            }
        }

        // Intercept submit event
        $preflightForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            if (isPreflightFormSubmitted) {
                return;
            }
            isPreflightFormSubmitted = true;

            const formData = $(`#${MOODLE_PREFLIGHT_FORM_ID}`).serialize();
            const autoProctorConsent = formData.includes("autoproctor_consent=1");

            if (autoProctorConsent) {
                // Close preflight form overlay
                const $cancelPreflightAttempt = $preflightForm.querySelector("#id_cancel");
                $cancelPreflightAttempt?.click();

                await _apInstance.setup(getProctoringOptions(_trackingOptions));
                await _apInstance.start();
                window.addEventListener("apMonitoringStarted", () => {
                    proctoringInProgress = true;
                    submitPreflightForm(e.target.action, $preflightForm, formData);
                });
            }
        });
    };

    /**
     * Submits the preflight form and handles the response
     * If the response contains a preflight form, it will submit that form instead
     * Otherwise, it will submit the preflight form as usual
     *
     * It will also show the iframe loader and remove the page loader and handle url changes in the iframe
     * @param {string} submitUrl
     * @param {object} $preflightForm
     * @param {string} formData
     */
    const submitPreflightForm = (submitUrl, $preflightForm, formData) => {
        $.ajax({
            url: submitUrl,
            method: "POST",
            data: formData,
        })
            .done((res) => {
                const parsedPreflightForm = $($.parseHTML(res)).find(`form[id="${MOODLE_PREFLIGHT_FORM_ID}"]`);
                if (parsedPreflightForm.length === 1) {
                    $preflightForm.submit();
                    return;
                }

                // Show loader while iframe loads
                $apIframeLoader.classList.remove("aptw-hidden");
                $preflightForm.setAttribute("target", IFRAME_NAME);
                $preflightForm.submit();

                onIframeUrlChanged(IFRAME_NAME, handleIframeUrlChange);

                $apIframe.classList.remove("aptw-hidden");

                document.querySelector("#page-wrapper")?.remove();
            })
            .fail(() => {
                isPreflightFormSubmitted = false;
            });
    };

    /**
     * Gets the file location from a URL
     * Example: host.com/mod/quiz/attempt.php?x=1&y=2 to attempt.php
     * @param {string} url
     * @returns {string}
     */
    const getUrlFileLocation = (url) => {
        const urlObject = new URL(url);
        return urlObject.pathname.split("/").pop();
    };

    /**
     * Waits for an element to appear in the document or iframe (if isInsideIframe is true)
     * and then calls a callback with the element. Times out after CONFIG.ELEMENT_WAIT_TIMEOUT_MS.
     * @param {string} selector - The CSS selector to search for
     * @param {Function} callback - Function to call with the found element as parameter
     * @param {boolean} isInsideIframe - Whether the element is inside the iframe
     */
    const waitForElement = (selector, callback, isInsideIframe = false) => {
        const startTime = Date.now();

        const elementIntervalId = setInterval(() => {
            try {
                const targetElement = isInsideIframe
                    ? document.getElementById(IFRAME_NAME)?.contentWindow?.document.querySelector(selector)
                    : document.querySelector(selector);

                if (targetElement) {
                    clearInterval(elementIntervalId);
                    callback(targetElement);
                    return;
                }

                // Check for timeout
                if (Date.now() - startTime > CONFIG.ELEMENT_WAIT_TIMEOUT_MS) {
                    clearInterval(elementIntervalId);
                    console.warn(`[AP] Element "${selector}" not found after ${CONFIG.ELEMENT_WAIT_TIMEOUT_MS}ms`);
                }
            } catch (err) {
                // Handle cross-origin errors when accessing iframe content
                clearInterval(elementIntervalId);
                console.error(`[AP] Error waiting for element "${selector}":`, err);
            }
        }, CONFIG.ELEMENT_WAIT_INTERVAL_MS);
    };

    /**
     * Initializes the AutoProctor instance and sets up event listeners for start and stop buttons.
     * @async
     * @function
     * @name initAutoProctor
     * @param {string} clientId
     * @param {string} hashedTestAttemptId - Pre-computed HMAC-SHA256 hash (base64 encoded)
     * @param {string} testAttemptId
     * @param {object} trackingOptions
     * @param {number} cmid
     * @param {string} lookupKey
     * @param {string} apDomain - The AutoProctor API domain
     * @param {string} apEnv - The environment (development/production)
     * @returns {Promise<void>}
     */
    // Track SDK loading retries
    let _sdkRetryCount = 0;

    /**
     * Disables the preflight form submit button and shows loading state.
     * This prevents users from starting the quiz before AutoProctor is ready.
     * @param {HTMLElement} $submitBtn - The submit button element
     * @param {boolean} disabled - Whether to disable or enable
     * @param {string} loadingText - Text to show while loading (optional)
     */
    function setSubmitButtonState($submitBtn, disabled, loadingText = null) {
        if (!$submitBtn) return;

        $submitBtn.disabled = disabled;

        if (disabled) {
            // Store original text and show loading state
            if (!$submitBtn.dataset.originalText) {
                $submitBtn.dataset.originalText = $submitBtn.textContent || $submitBtn.value;
            }
            const text = loadingText || "Loading AutoProctor...";
            if ($submitBtn.tagName === "INPUT") {
                $submitBtn.value = text;
            } else {
                $submitBtn.textContent = text;
            }
            $submitBtn.style.opacity = "0.7";
            $submitBtn.style.cursor = "not-allowed";
        } else {
            // Restore original text
            const originalText = $submitBtn.dataset.originalText;
            if (originalText) {
                if ($submitBtn.tagName === "INPUT") {
                    $submitBtn.value = originalText;
                } else {
                    $submitBtn.textContent = originalText;
                }
            }
            $submitBtn.style.opacity = "";
            $submitBtn.style.cursor = "";
        }
    }

    /**
     * Finds and disables all quiz start/attempt/preview buttons on the page.
     * This includes both the main page buttons and preflight form submit.
     * @returns {HTMLElement[]} Array of disabled button elements
     */
    function disableQuizStartButtons() {
        const buttons = [];

        // Find submit button in preflight form (if form is open)
        const preflightSubmit = document.querySelector(`#${MOODLE_PREFLIGHT_FORM_ID} input[type="submit"]`);
        if (preflightSubmit) {
            setSubmitButtonState(preflightSubmit, true);
            buttons.push(preflightSubmit);
        }

        // Find main page quiz buttons (Preview quiz, Attempt quiz, Re-attempt quiz, Continue)
        // These are typically in a form with action pointing to startattempt.php
        const startAttemptForms = document.querySelectorAll('form[action*="startattempt.php"]');
        startAttemptForms.forEach(form => {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                setSubmitButtonState(submitBtn, true);
                buttons.push(submitBtn);
            }
        });

        // Also look for direct links/buttons that might start attempts
        const quizButtons = document.querySelectorAll('.quizattempt button, .quizstartbuttondiv button');
        quizButtons.forEach(btn => {
            setSubmitButtonState(btn, true);
            buttons.push(btn);
        });

        return buttons;
    }

    /**
     * Re-enables all previously disabled quiz start buttons.
     * @param {HTMLElement[]} buttons - Array of button elements to enable
     */
    function enableQuizStartButtons(buttons) {
        buttons.forEach(btn => setSubmitButtonState(btn, false));
    }

    // Track disabled buttons across retries
    let _disabledButtons = [];

    async function initAutoProctor(clientId, hashedTestAttemptId, testAttemptId, trackingOptions, cmid, lookupKey, apDomain, apEnv, userDetails) {
        // Don't initialize if we're inside an iframe (prevents double initialization on redirect pages)
        if (window !== window.top) {
            return;
        }

        // Immediately try to disable all quiz start buttons to prevent bypass
        // This runs on every retry to catch when buttons become available
        const newButtons = disableQuizStartButtons();
        // Add any newly found buttons to our tracking array
        newButtons.forEach(btn => {
            if (!_disabledButtons.includes(btn)) {
                _disabledButtons.push(btn);
            }
        });

        // Check if AutoProctor is already loaded and retry if not
        // Also verify it's actually a constructor (not just an object from failed AMD load)
        if (typeof window.AutoProctor === "undefined" || typeof window.AutoProctor !== "function") {
            _sdkRetryCount++;

            if (_sdkRetryCount > CONFIG.SDK_MAX_RETRIES) {
                console.error(`[AP] AutoProctor SDK failed to load after ${CONFIG.SDK_MAX_RETRIES} attempts`);
                // Show error to user and keep buttons disabled
                const errorDiv = document.createElement("div");
                errorDiv.className = "alert alert-danger";
                errorDiv.style.cssText = "position: fixed; top: 70px; left: 0; right: 0; margin: 0 20px; padding: 15px; z-index: 9999;";
                const textNode = document.createTextNode("Unable to start proctoring. Please ");
                const refreshLink = document.createElement("a");
                refreshLink.href = "#";
                refreshLink.textContent = "refresh the page";
                refreshLink.addEventListener("click", function(e) {
                    e.preventDefault();
                    location.reload();
                });
                errorDiv.appendChild(textNode);
                errorDiv.appendChild(refreshLink);
                errorDiv.appendChild(document.createTextNode(" to try again."));
                document.body.prepend(errorDiv);

                // Update all buttons to show error state (keep disabled)
                _disabledButtons.forEach(btn => {
                    setSubmitButtonState(btn, true, "Proctoring Failed - Refresh Page");
                });
                return;
            }

            console.log(`[AP] Waiting for AutoProctor SDK (attempt ${_sdkRetryCount}/${CONFIG.SDK_MAX_RETRIES})...`);
            setTimeout(
                () => initAutoProctor(clientId, hashedTestAttemptId, testAttemptId, trackingOptions, cmid, lookupKey, apDomain, apEnv, userDetails),
                CONFIG.SDK_RETRY_DELAY_MS
            );
            return;
        }

        // Cache variables in execution context
        _trackingOptions = trackingOptions ?? {};
        _testAttemptId = testAttemptId ?? "";
        _lookupKey = lookupKey ?? "";
        _apDomain = apDomain ?? "https://autoproctor.co";
        _apEnv = apEnv ?? "production";
        _userDetails = userDetails ?? null;

        // Initialize AutoProctor instance
        const credentials = getCredentials(clientId, hashedTestAttemptId, testAttemptId, _apDomain, _apEnv);
        _apInstance = new window.AutoProctor(credentials);

        addIframe();
        addPageLoader();

        // AutoProctor is ready - re-enable all previously disabled buttons
        enableQuizStartButtons(_disabledButtons);
        _disabledButtons = [];

        waitForElement(`#${MOODLE_PREFLIGHT_FORM_ID}`, ($targetElement) => {
            addSubmitPreflightEvent($targetElement);

            // Prevent user to directly access attempt.php by overriding url to view.php
            const isOnStartattempt = ["startattempt.php", "attempt.php"].includes(
                getUrlFileLocation(window.location.href)
            );
            if (isOnStartattempt) {
                window.history.replaceState({}, "", `/mod/quiz/view.php?id=${cmid}`);
            }
        });
    }

    /**
     * Loads the report for the given test attempt ID.
     * @param {string} clientId
     * @param {string} hashedTestAttemptId - Pre-computed HMAC-SHA256 hash (base64 encoded)
     * @param {string} testAttemptId
     * @param {string} apDomain - The AutoProctor API domain
     * @param {string} apEnv - The environment (development/production)
     * @param {{name: string, email: string}} [userDetails] - Optional user details for the report
     * @returns {void}
     */
    // Track SDK loading retries for loadReport
    let _reportSdkRetryCount = 0;

    function loadReport(clientId, hashedTestAttemptId, testAttemptId, apDomain, apEnv, userDetails) {
        // Check if AutoProctor is already loaded and retry if not
        if (typeof window.AutoProctor === "undefined" || typeof window.AutoProctor !== "function") {
            _reportSdkRetryCount++;

            if (_reportSdkRetryCount > CONFIG.SDK_MAX_RETRIES) {
                console.error(`[AP] AutoProctor SDK failed to load for report after ${CONFIG.SDK_MAX_RETRIES} attempts`);
                const loaderEl = document.getElementById("ap-report-loader");
                if (loaderEl) {
                    loaderEl.textContent = "";
                    const alertDiv = document.createElement("div");
                    alertDiv.className = "alert alert-danger";
                    alertDiv.textContent = "Failed to load AutoProctor SDK. ";
                    const refreshLink = document.createElement("a");
                    refreshLink.href = "#";
                    refreshLink.textContent = "Refresh page";
                    refreshLink.addEventListener("click", function(e) {
                        e.preventDefault();
                        location.reload();
                    });
                    alertDiv.appendChild(refreshLink);
                    alertDiv.appendChild(document.createTextNode(" to try again."));
                    loaderEl.appendChild(alertDiv);
                }
                return;
            }

            console.log(`[AP] Waiting for AutoProctor SDK for report (attempt ${_reportSdkRetryCount}/${CONFIG.SDK_MAX_RETRIES})...`);
            setTimeout(() => loadReport(clientId, hashedTestAttemptId, testAttemptId, apDomain, apEnv), CONFIG.SDK_RETRY_DELAY_MS);
            return;
        }

        const credentials = getCredentials(clientId, hashedTestAttemptId, testAttemptId, apDomain, apEnv);
        const apInstance = new window.AutoProctor(credentials);
        apInstance.showReport(getReportOptions(true, userDetails));
    }

    /**
     * Adds a tabbed interface to the quiz review page with Test Summary, Proctoring Summary, and Session Recording tabs.
     *
     * This function transforms the Moodle quiz review page DOM to display results in a tabbed interface.
     * It's called from PHP via js_call_amd when the user views the review.php page.
     *
     * DOM STRUCTURE EXPLANATION:
     * --------------------------
     * Moodle's review page has this structure:
     *
     *   <div id="region-main">                          (grandparent)
     *       <div class="mb-3">                          (summaryWrapper)
     *           <table class="quizreviewsummary">       (targetElement - attempt info table)
     *       </div>
     *       <div id="question-1">...</div>              (quiz questions - siblings of summaryWrapper)
     *       <div id="question-2">...</div>
     *       <div class="submitbtns">...</div>
     *   </div>
     *
     * AFTER TRANSFORMATION:
     * ---------------------
     *   <div id="region-main">
     *       <div class="mb-3">                          (stays at top - always visible)
     *           <table class="quizreviewsummary">
     *       </div>
     *       <div id="ap-tabs-container">                (tab navigation bar)
     *           [Test Summary] [Proctoring] [Recording] [Open Report link]
     *       </div>
     *       <div id="ap-test-summary-content">          (visible when Tab 1 active)
     *           <div id="question-1">...</div>          (moved here from grandparent)
     *           <div id="question-2">...</div>
     *           <div class="submitbtns">...</div>
     *       </div>
     *       <div id="ap-report-content">                (visible when Tab 2 active)
     *           <div id="ap-report__overview">          (AutoProctor overview - filled by SDK)
     *           <div id="ap-report__proctor">           (AutoProctor details - filled by SDK)
     *       </div>
     *       <div id="ap-report__session">               (visible when Tab 3 active - filled by SDK)
     *       </div>
     *   </div>
     *
     * KEY IMPLEMENTATION NOTES:
     * - The proctoring report is lazy-loaded only when the Proctoring Summary tab is clicked
     * - AutoProctor SDK fills in ap-report__overview, ap-report__proctor, and ap-report__session
     * - Tab switching simply toggles display:block/none on the content containers
     *
     * @param {string} reportUrl - The URL to the report (fallback external link)
     * @param {string} buttonLabel - The label for the button (unused, kept for compatibility)
     * @param {string} clientId - The AutoProctor client ID
     * @param {string} hashedTestAttemptId - Pre-computed HMAC-SHA256 hash (base64 encoded)
     * @param {string} testAttemptId - The test attempt ID for this session
     * @param {object} trackingOptions - The tracking options used for this session (to determine which tabs to show)
     * @param {string} apDomain - The AutoProctor API domain
     * @param {string} apEnv - The environment (development/production)
     * @param {{name: string, email: string}} [userDetails] - Optional user details to be passed to AutoProctor
     */
    function addReportButton(reportUrl, buttonLabel, clientId, hashedTestAttemptId, testAttemptId, trackingOptions, apDomain, apEnv, userDetails) {
        // Determine if session recording was enabled for this attempt
        const showSessionRecording = trackingOptions?.recordSession !== false;
        // Track if report has been loaded
        let reportLoaded = false;

        // Tab styles
        const activeTabStyle = "border-bottom: 3px solid #106bbf; color: #106bbf; padding-bottom: 8px; font-weight: 600;";
        const inactiveTabStyle = "border-bottom: 3px solid transparent; color: #6b7280; padding-bottom: 8px; font-weight: 600;";

        // Tab switching helper function
        const switchTab = (activeTabId) => {
            // Only include session recording tab if it was enabled
            const tabs = showSessionRecording
                ? ["test-summary-tab", "proctoring-summary-tab", "session-recording-tab"]
                : ["test-summary-tab", "proctoring-summary-tab"];
            const contents = showSessionRecording
                ? ["ap-test-summary-content", "ap-report-content", "ap-report__session"]
                : ["ap-test-summary-content", "ap-report-content"];

            tabs.forEach((tabId, index) => {
                const tab = document.getElementById(tabId);
                const content = document.getElementById(contents[index]);

                if (!tab || !content) {
                    return;
                }

                if (tabId === activeTabId) {
                    tab.style.cssText = activeTabStyle + " cursor: pointer; white-space: nowrap;";
                    content.style.display = "block";

                    // Load proctoring report when switching to proctoring tab (lazy load)
                    if (tabId === "proctoring-summary-tab" && !reportLoaded) {
                        reportLoaded = true;
                        loadReport(clientId, hashedTestAttemptId, testAttemptId, apDomain, apEnv, userDetails);

                        // Hide loader and show content after a delay
                        setTimeout(() => {
                            const loader = document.getElementById("ap-report-loader");
                            const proctoringContent = document.getElementById("ap-proctoring-content");
                            if (loader) {
                                loader.style.display = "none";
                            }
                            if (proctoringContent) {
                                proctoringContent.style.display = "block";
                            }
                        }, 2000);
                    }
                } else {
                    tab.style.cssText = inactiveTabStyle + " cursor: pointer; white-space: nowrap;";
                    content.style.display = "none";
                }
            });
        };

        // Wait for DOM to be ready
        const insertTabs = () => {
            // Find the quiz info table or summary section
            const summaryTable = document.querySelector(".quizreviewsummary");
            const attemptInfo = document.querySelector(".mod_quiz-attempt-summary");
            const targetElement = summaryTable || attemptInfo;

            if (!targetElement) {
                // Retry if the target element is not found yet
                setTimeout(insertTabs, 500);
                return;
            }

            // Check if tabs already exist
            if (document.getElementById("ap-tabs-container")) {
                return;
            }

            // The summary table is inside a wrapper div (e.g., div.mb-3)
            // The quiz questions are siblings of that wrapper, not children
            // So we need to go up to the grandparent level
            const summaryWrapper = targetElement.parentElement; // div.mb-3
            const mainContent = summaryWrapper.parentElement;   // parent that contains both wrapper and questions

            // Create tabs bar
            const tabsBar = document.createElement("div");
            tabsBar.id = "ap-tabs-container";
            tabsBar.style.cssText = "margin: 20px 0;";
            // Build session recording tab HTML only if enabled
            const sessionRecordingTabHtml = showSessionRecording
                ? `<div id="session-recording-tab"
                         style="${inactiveTabStyle} cursor: pointer; white-space: nowrap;">
                        Session Recording
                    </div>`
                : "";

            tabsBar.innerHTML = `
                <div id="ap-report-tabs" style="display: flex; gap: 24px; font-size: 16px;
                            background: white; border: 1px solid #e5e7eb; border-radius: 8px;
                            padding: 12px 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div id="test-summary-tab"
                         style="${activeTabStyle} cursor: pointer; white-space: nowrap;">
                        Test Summary
                    </div>
                    <div id="proctoring-summary-tab"
                         style="${inactiveTabStyle} cursor: pointer; white-space: nowrap;">
                        Proctoring Summary
                    </div>
                    ${sessionRecordingTabHtml}
                    <a href="${reportUrl}" target="_blank"
                       style="margin-left: auto; color: #106bbf; font-size: 14px; text-decoration: none;
                              display: flex; align-items: center; gap: 4px;">
                        <i class="fa fa-external-link"></i> Open Report
                    </a>
                </div>
            `;

            // Create test summary wrapper
            const testSummaryWrapper = document.createElement("div");
            testSummaryWrapper.id = "ap-test-summary-content";

            // Create proctoring content container
            const proctoringContainer = document.createElement("div");
            proctoringContainer.id = "ap-report-content";
            proctoringContainer.style.cssText = "display: none;";
            proctoringContainer.innerHTML = `
                <div id="ap-report-loader" style="text-align: center; padding: 40px;">
                    <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3;
                                border-top: 4px solid #106bbf; border-radius: 50%; animation: ap-spin 1s linear infinite;">
                    </div>
                    <p style="margin-top: 16px; color: #6b7280;">Loading proctoring report...</p>
                    <style>
                        @keyframes ap-spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                </div>
                <div id="ap-proctoring-content" style="display: none;">
                    <div id="ap-report__overview" style="margin-bottom: 20px;"></div>
                    <div id="ap-report__proctor"></div>
                </div>
            `;

            // Create session recording container only if enabled
            let sessionContainer = null;
            if (showSessionRecording) {
                sessionContainer = document.createElement("div");
                sessionContainer.id = "ap-report__session";
                sessionContainer.style.cssText = "display: none;";
            }

            // Collect all siblings that come AFTER the summaryWrapper (questions, submit buttons, etc.)
            const allChildren = Array.from(mainContent.children);
            const summaryWrapperIndex = allChildren.indexOf(summaryWrapper);
            const contentToWrap = allChildren.filter((el, index) =>
                index > summaryWrapperIndex &&
                el.id !== "ap-tabs-container" &&
                el.id !== "ap-test-summary-content" &&
                el.id !== "ap-report-content" &&
                el.id !== "ap-report__session"
            );

            // Insert tabs bar after the summary wrapper
            summaryWrapper.after(tabsBar);

            // Insert content containers after tabs bar
            tabsBar.after(testSummaryWrapper);
            testSummaryWrapper.after(proctoringContainer);
            if (sessionContainer) {
                proctoringContainer.after(sessionContainer);
            }

            // Move all the quiz content (questions, etc.) into test summary wrapper
            contentToWrap.forEach(element => {
                testSummaryWrapper.appendChild(element);
            });

            // Set up tab click handlers
            const testTab = document.getElementById("test-summary-tab");
            const proctoringTab = document.getElementById("proctoring-summary-tab");

            testTab?.addEventListener("click", () => switchTab("test-summary-tab"));
            proctoringTab?.addEventListener("click", () => switchTab("proctoring-summary-tab"));

            // Only add session recording tab listener if it exists
            if (showSessionRecording) {
                const sessionTab = document.getElementById("session-recording-tab");
                sessionTab?.addEventListener("click", () => switchTab("session-recording-tab"));
            }
        };

        // Start trying to insert the tabs
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", insertTabs);
        } else {
            insertTabs();
        }
    }

    return {
        init: initAutoProctor,
        loadReport: loadReport,
        addReportButton: addReportButton,
    };
});
