
var offsetYToHideHeader = 50;

var headerArea = null;
var topArea = null;
var quickLookArea = null;
var isTouchDevice = false;

var doseHideHeader = false;

window.onload = function () {
    headerArea = document.querySelector("#HeaderArea");
    topArea = document.querySelector("#TopArea");
    quickLookArea = document.querySelector("#QuickLookArea");
    window.addEventListener("scroll", OnScroll);
    isTouchDevice = IsTouchDevice();
}


function OnScroll() {
    if (window.pageYOffset > offsetYToHideHeader) {
        if (!doseHideHeader) {
            headerArea.style.animationName = "HeaderAreaDisappear";
            headerArea.style.animationDuration = "1s";
            headerArea.style.left = "100%";

            topArea.style.animationName = "TopAreaSlideUp";
            topArea.style.animationDuration = "1s";
            topArea.style.top = "-50px";
            doseHideHeader = true;
        }

    }
    else {
        if (doseHideHeader) {
            headerArea.style.animationName = "HeaderAreaAppear";
            headerArea.style.animationDuration = "1s";
            headerArea.style.left = "0%";

            topArea.style.animationName = "TopAreaSlideDown";
            topArea.style.animationDuration = "1s";
            topArea.style.top = "0px";
            doseHideHeader = false;
        }

    }
}

function QuickLookMouse(target) {
    if (!isTouchDevice) {
        QuickLook(target);
    }
}
function QuickLookTouch(target) {
    if (isTouchDevice) {
        QuickLook(target);
    }
}

function ExitQuickLookMouse() {
    if (!isTouchDevice) {
        ExitQuickLook();
    }
}
function ExitQuickLookTouch() {
    if (isTouchDevice) {
        ExitQuickLook();
    }
}

function QuickLook(target) {
    if (quickLookArea.firstChild != null) {
        quickLookArea.firstChild.style.display = "none";
        document.body.appendChild(quickLookArea.firstChild);
    }
    var content = null;

    switch (target) {
        case "RightContent":
            content = document.querySelector("#RightContentContainer");
            if (content == null) {
                return;
            }
            quickLookArea.style.animationName = "QuickLookAreaSlideInFromBottomRight";

            break;

        case "LeftContent":
            content = document.querySelector("#LeftContentContainer");
            if (content == null) {
                return;
            }
            quickLookArea.style.animationName = "QuickLookAreaSlideInFromBottomLeft";

            break;

        default:
            var id = parseInt(target.replace(/[^0-9^\.]/g, ""), 10);
            content = document.querySelector("#ChildContent" + id + "Container");
            if (content == null) {
                return;
            }
            quickLookArea.style.animationName = "QuickLookAreaFadeIn";
            break;
    }
    quickLookArea.appendChild(content);
    quickLookArea.style.animationDuration = "1s";
    quickLookArea.removeEventListener("animationend", QuickLookFadeOutHelper);
    //quickLookArea.style.animationDelay = "1s";
    //quickLookArea.style.animationFillMode = "forwards";
    quickLookArea.style.display = "block";
    content.style.display = "block";
}

function ExitQuickLook() {
    //quickLookArea.style.animationFillMode = "forwards";
    quickLookArea.style.animationName = "QuickLookAreaFadeOut";
    //quickLookArea.style.animationDelay = "0s";
    quickLookArea.style.animationDuration = "1s";
    quickLookArea.addEventListener("animationend", QuickLookFadeOutHelper);
}

function QuickLookFadeOutHelper() {
    quickLookArea.style.display = "none";
    quickLookArea.removeEventListener("animationend", QuickLookFadeOutHelper);
}

function IsTouchDevice() {
    var result = false;
    if (window.ontouchstart === null) {
        result = true;
    }
    return result;
}