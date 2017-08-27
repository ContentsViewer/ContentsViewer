﻿
var offsetYToHideHeader = 50;

var headerArea = null;
var topArea = null;
var leftSideArea = null;
var quickLookArea = null;
var indexArea = null;
var isTouchDevice = false;

var doseHideHeader = false;

var sectionListInRootContent = [];
var sectionListInIndexArea = [];

var timer = null;

window.onload = function () {
	// 各Area取得
	headerArea = document.querySelector("#HeaderArea");
	topArea = document.querySelector("#TopArea");
	quickLookArea = document.querySelector("#QuickLookArea");
	leftSideArea = document.querySelector("#LeftSideArea");

	// Scrollイベント登録
	window.addEventListener("scroll", OnScroll);

	// タッチデバイス判定
	isTouchDevice = IsTouchDevice();

	// --- indexArea関係 --------------------------------------------
	var indexArea = document.getElementById("IndexArea");
	var indexAreaOnSmallScreen = document.getElementById("IndexAreaOnSmallScreen");
	var rootContent = document.getElementById("RootContentField");

	// indexAreaとrootContentが正常に読み込めた場合のみ実行
	if (rootContent && indexArea) {
		// IndexArea内にあるNaviを取得
		var naviInIndexArea = null;
		if (indexArea.getElementsByClassName("Navi").length > 0) {
			var naviInIndexArea = indexArea.getElementsByClassName("Navi")[0];
		}

		// Naviを取得できた場合のみ実行
		if (naviInIndexArea) {
			var totalID = 0;
			if ((totalID = CreateSectionTreeHelper(rootContent, naviInIndexArea, 0)) == 0) {
				naviInIndexArea.innerText = "  目次がありません";
			}

			//alert(indexAreaOnSmallScreen);
			if (indexAreaOnSmallScreen) {
				var naviInIndexAreaOnSmallScreen = naviInIndexArea.cloneNode(true);
				naviInIndexAreaOnSmallScreen.removeAttribute("class");
				indexAreaOnSmallScreen.appendChild(naviInIndexAreaOnSmallScreen);
				//alert("12");
			}
			//alert(totalID);
			//alert("1");
		}
		/*
		
		
			alert(naviInIndexArea);

			// RootContent内にあるSectionごとの処理
			for (var i = 0; i < sectionListInRootContent.length; i++) {
				var section = document.createElement("li");
				section.innerText = sectionListInRootContent[i].innerText;
				naviInIndexArea.appendChild(section);
			}
		}
		*/
	}


	//alert(rootContent);
}

//
// RootContent内にあるSectionを取得します. 
// 同時に, ナヴィゲータの作成, sectionListInRootContent, sectionListInIndexAreaにSectionを登録します.
//
// @param element:
//  Section探索元
//  この下の階層にSectionListが来るようにしてください
//  
// @param navi:
//  生成されるナヴィゲータリスト
//  
// @param idBegin:
//  振り分け開始id
//
function CreateSectionTreeHelper(element, navi, idBegin) {

	for (var i = 0; i < element.children.length; i++) {

		if (element.children[i].className == "SectionList") {
			var ulElement = document.createElement("ul");

			// UL内の各LIをイテレート
			for (var j = 0; j < element.children[i].children.length; j++) {
				// LI内の各子要素をイテレート
				for (var k = 0; k < element.children[i].children[j].children.length; k++) {
					// Class名Section, SubSectionのとき題名取得
					if (element.children[i].children[j].children[k].className == "Section"
						|| element.children[i].children[j].children[k].className == "SubSection") {
						//alert(element.children[i].children[j].children[k].innerText);

						element.children[i].children[j].children[k].setAttribute("id", "SectionID_" + idBegin);
						sectionListInRootContent.push(element.children[i].children[j]);

						var section = document.createElement("li");
						var link = document.createElement("a");
						link.innerText = element.children[i].children[j].children[k].innerText;
						link.href = "#SectionID_" + idBegin;
						section.appendChild(link);

						sectionListInIndexArea.push(link);

						idBegin = CreateSectionTreeHelper(element.children[i].children[j], section, ++idBegin);
						ulElement.appendChild(section);


						// 一つのSectionに二つ以上の名前が存在することはないので, 一つ見つけたらループを抜ける.
						break;
					}
				}
			}
			navi.appendChild(ulElement);


			// SectionListが同階層に二つ以上存在することはないので,
			// 少なくとも一つ見つけたらループを抜ける.
			break;
		}
	}
	return idBegin;
}

function OnScroll() {
	if (timer) {
		return;
	}
	//clearTimeout(timer);
	timer = setTimeout(function () {
		timer = null;

		//一定量スクロールされたとき
		if (window.pageYOffset > offsetYToHideHeader) {
			if (!doseHideHeader) {
				headerArea.style.animationName = "HeaderAreaDisappear";
				headerArea.style.animationDuration = "1s";
				headerArea.style.width = "0px";

				topArea.style.animationName = "TopAreaSlideUp";
				topArea.style.animationDuration = "1s";
				topArea.style.top = "-50px";

				leftSideArea.style.top = "70px";
				leftSideArea.style.animationName = "LeftSideAreaRiseUp";
				leftSideArea.style.animationDuration = "1s";
				doseHideHeader = true;
			}

		}
		else {
			if (doseHideHeader) {
				headerArea.style.animationName = "HeaderAreaAppear";
				headerArea.style.animationDuration = "1s";
				headerArea.style.width = "100%";

				topArea.style.animationName = "TopAreaSlideDown";
				topArea.style.animationDuration = "1s";
				topArea.style.top = "0px";

				leftSideArea.style.top = "120px";
				leftSideArea.style.animationName = "LeftSideAreaRiseDown";
				leftSideArea.style.animationDuration = "1s";
				doseHideHeader = false;
			}

		}
		//alert("1");

		var currentSectionIDs = [];
		for (var i = 0; i < sectionListInRootContent.length; i++) {
			var sectionRect = sectionListInRootContent[i].getBoundingClientRect();
			if (sectionRect.top < window.innerHeight / 2 && sectionRect.bottom > window.innerHeight / 2) {
				currentSectionIDs.push(i);
			}
		}

		for (var i = 0; i < sectionListInIndexArea.length; i++) {
			sectionListInIndexArea[i].removeAttribute("class");
		}

		for (var i = 0; i < currentSectionIDs.length; i++) {
			sectionListInIndexArea[currentSectionIDs[i]].setAttribute("class", "Selected");
		}
	}, 500);
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