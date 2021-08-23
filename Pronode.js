const Pronode = {};

/**
	Modify default anchor-click behavior:
 */
Pronode.anchorClickCallback = function(e) {
	
    var e = window.e || e;

    if (e.target.tagName !== 'A') 
        return;

	const a = e.target;
	
	if (a.attributes.pn_xhr && a.attributes.pn_xhr.value == "false") // Normal full-page reload if pn_xhr attribute is set to false:
		return;
		
	if (a.origin != window.origin) // Skip external links
		return;
		
	e.preventDefault();
	Pronode.makeRequest(a.pathname);
	
	if (a.attributes.pn_scrollup) { //  Scroll page to top if pn_scrollup attribute is set
		
		document.addEventListener('pn_complete', () => { Pronode.scrollUp() }, { once: true });
	}
}


/**
	Modify default form submit behavior:
 */
Pronode.formSubmitCallback = function(e) {
	
    var e = window.e || e;

	if (e.target.tagName !== 'FORM')
		return;

	const form = e.target;
	
	// domain check here?
	
	e.preventDefault();
	Pronode.makeRequest(form.action, form.method, new FormData(form));
}


/**
	Popstate callback fired when back and forward buttons are clicked
 */
Pronode.popstateCallback = function(e) {

	document.documentElement.innerHTML = event.state.html;
	Pronode.drawProgressBar(0);
}


/**
	Add click and sumbit listeners:
 */
try {
	
	// document.addEventListener('pn_complete', () => { hljs.highlightAll() }, false);
	
	document.addEventListener('click',  Pronode.anchorClickCallback, false);
	document.addEventListener('submit', Pronode.formSubmitCallback, false);
	
	window.addEventListener('popstate', Pronode.popstateCallback);
	
} catch(e) {
	
	console.log(e);
}
    

/**
	Update state
 */
Pronode.updateState = function(url) {
	
	const state = { html: document.documentElement.innerHTML, pageTitle: document.title};
	
	if (url == window.location.href) {
		
		window.history.replaceState(state, document.title, url);
		return;
	}
	
	window.history.pushState(state, document.title, url);
}





/**
	Perform XHR to server
 */
Pronode.makeRequest = function(url, method = 'GET', formData = null) {
	
	const xhr = new XMLHttpRequest();
	
	xhr.open(method, url, true);
	xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	//xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xhr.withCredentials = true;
	
	xhr.onloadend = function() {
		
		xhr.time = (new Date() - xhr.startTime);
		
		console.log("Pronode: loaded " + url+ " in "+ xhr.time + " ms")
		
		try {
			
			const response = JSON.parse(xhr.responseText);
			
			Pronode.drawProgressBar(75);
			
			Pronode.updateFragments(response);
			Pronode.dispatchEvent('pn_complete');
			Pronode.updateState(url);
			
			Pronode.drawProgressBar(100);
			
		} catch (e) {
			
			console.log("Pronode ERROR: response must be a valid JSON. " + e, xhr.responseText);
			
			Pronode.drawProgressBar(75, 500);
			Pronode.drawErrorBar(25, 500);
		}
		
	}
	
	Pronode.drawProgressBar(0, null, 75);
	
	xhr.startTime = new Date();
	xhr.send(formData);
	
	return xhr;
};


Pronode.dispatchEvent = function(eventName) {
	
	const event = new Event(eventName);
	
	document.dispatchEvent(event);
}


/**
	Mass-update Pronode HTML chunks (pn_fragment) received from server.
 */
Pronode.updateFragments = function(fragments) {
	
	console.log(fragments);
	
	fragments.forEach(function(fragment) {
		
		Pronode.updateFragment(fragment);
		
	});
};


/**
	Update a single Pronode HTML chunk (pn_fragment) received from server.
 */
Pronode.updateFragment = function(fragment) {
	
	var query = '[pn_fragment="' + fragment.pn_fragment + '"][pn_origin="' + fragment.pn_origin + '"]'; 
	var element = document.querySelector(query);
	
	if (!element) {
		
		console.log("Pronode ERROR: Couldn't find fragment with " + query);
		return false;
	}
	
	if (fragment.htmlPlacement == 'inner') {
		
		// not supported yet
		element.outerHTML = fragment.html;
		
	} else {
		
		element.outerHTML = fragment.html;
	}
	
	return true;
};


Pronode.getInner = function(htmlString) {}; // TODO, get inner html of Fragment sent


/**
	Draw Pronode progress bar for given state
 */
Pronode.drawProgressBar = function(progress, autohide, animateTo) {
	
	this.progress = progress;
	
	if (document.getElementById('pn_progress_bar') === null) {
		
		const progressBarNode = document.createElement('div');
		      progressBarNode.id = 'pn_progress_bar';
		      progressBarNode.style.position = 'fixed';
		      progressBarNode.style.top = 0;
		      progressBarNode.style.left = 0;
		      progressBarNode.style.height = '1px';
		      progressBarNode.style.zIndex = 999;
		      progressBarNode.style.backgroundColor = 'CornflowerBlue';

		document.getElementsByTagName('body')[0].appendChild(progressBarNode);
		
		return Pronode.drawProgressBar(progress);
	}
	
	const progressBarNode = document.getElementById('pn_progress_bar');
	
	progressBarNode.style.width = progress + '%';
	
	if (animateTo) {
		
		progressBarNode.animate([{ width: progress + '%' }, { width: animateTo + '%' }], 200);
	}
	
	if (autohide || progress == 100) {
		
		if (!autohide) autohide = 100;
		
		Pronode.progressTimeout = setTimeout(() => {Pronode.drawProgressBar(0)}, autohide);
	}
}


/**
	Draw Pronode error bar for given state
 */
Pronode.drawErrorBar = function(progress, autohide) {
	
	if (document.getElementById('pn_error_bar') === null) {
		
		const errorBarNode = document.createElement('div');
		      errorBarNode.id = 'pn_error_bar';
		      errorBarNode.style.position = 'fixed';
		      errorBarNode.style.top = 0;
		      errorBarNode.style.right = 0;
		      errorBarNode.style.height = '1px';
		      errorBarNode.style.zIndex = 999;
		      errorBarNode.style.backgroundColor = 'Crimson';

		document.getElementsByTagName('body')[0].appendChild(errorBarNode);
		
		return Pronode.drawErrorBar(progress, autohide);
	}
	
	const progressBarNode = document.getElementById('pn_error_bar');
	
	progressBarNode.style.width = progress + '%';
	
	if (autohide || progress == 100) {
		
		if (!autohide) autohide = 100;
		
		Pronode.errorTimeout = setTimeout(() => {Pronode.drawErrorBar(0)}, autohide);
	}	
}


/**
	Scroll page to the top
 */
Pronode.scrollUp = function() {
	
	return window.scrollTo({ top: 0, behavior: 'smooth' });
}

