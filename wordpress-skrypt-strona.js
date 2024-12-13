// inituje local storage jak nie istnieje
let empty = JSON.stringify([]);
if (!localStorage.getItem('suple')) {
    localStorage.setItem("suple", empty);
}
if (!localStorage.getItem('odzywki')) {
    localStorage.setItem("odzywki", empty);
}

// appenduje z local storage do htmla
window.onload = init;

function init() {
    // suple
    var itemsRetrieved = JSON.parse(localStorage.getItem('suple'));
    var ul = document.getElementById("suple");
    itemsRetrieved.forEach(item => {
        var li = document.createElement("li");
        var t = li.appendChild(document.createTextNode(item[0]));
        if (item[1] !== "") {
            t.nodeValue = `${t.nodeValue}, `;
            var a = li.appendChild(document.createElement('a'));
            let link = document.createTextNode(item[1]);
            a.appendChild(link);
            a.href = item[1];
        }
        var button = document.createElement('button');
        button.innerHTML = '[X]';
        var button2 = document.createElement('input');
        button2.type = 'button'
        button2.value = 'Dodaj do słów kluczowych';
        button2.onclick = appendtoinput;
        li.appendChild(button);
        li.appendChild(button2);
        ul.appendChild(li);
    });
    // odzywki

    var itemsRetrieved = JSON.parse(localStorage.getItem('odzywki'));
    var ul = document.getElementById("odzywki");
    itemsRetrieved.forEach(item => {
        var li = document.createElement("li");
        var t = li.appendChild(document.createTextNode(item[0]));
        if (item[1] !== "") {
            t.nodeValue = `${t.nodeValue}, `;
            var a = li.appendChild(document.createElement('a'));
            let link = document.createTextNode(item[1]);
            a.appendChild(link);
            a.href = item[1];
        }
        var button = document.createElement('button');
        button.innerHTML = '[X]';
        var button2 = document.createElement('input');
        button2.type = 'button'
        button2.value = 'Dodaj do słów kluczowych';
        button2.onclick = appendtoinput;
        li.appendChild(button);
        li.appendChild(button2);
        ul.appendChild(li);
    });

}
function addwordsuple() {
    var z = getInputValue();
    var l = getLinkInputValue();

    let itemsRetrieved = JSON.parse(localStorage.getItem('suple'));
    if (l !== "") {
        itemsRetrieved.push([z, l]);
    }
    else {
        itemsRetrieved.push([z, ""]);
    }
    localStorage.setItem('suple', JSON.stringify(itemsRetrieved));
    var ul = document.getElementById("suple");
    var li = document.createElement("li");
    var t = li.appendChild(document.createTextNode(z));
    if (l !== "") {
        t.nodeValue = `${t.nodeValue}, `;
        var a = li.appendChild(document.createElement('a'));
        let link = document.createTextNode(l);
        a.appendChild(link);
        a.href = l;
    }
    var button = document.createElement('button');
    button.innerHTML = '[X]';
    var button2 = document.createElement('input');
    button2.type = 'button'
    button2.value = 'Dodaj do słów kluczowych';
    button2.onclick = appendtoinput;
    li.appendChild(button);
    li.appendChild(button2);
    ul.appendChild(li);
}

function addwordodzywki() {
    var z = getInputValue();
    var l = getLinkInputValue();

    let itemsRetrieved = JSON.parse(localStorage.getItem('odzywki'));
    if (l !== "") {
        itemsRetrieved.push([z, l]);
    }
    else {
        itemsRetrieved.push([z, ""]);
    }
    localStorage.setItem('odzywki', JSON.stringify(itemsRetrieved));
    var ul = document.getElementById("odzywki");
    var li = document.createElement("li");
    var t = li.appendChild(document.createTextNode(z));
    if (l !== "") {
        t.nodeValue = `${t.nodeValue}, `;
        var a = li.appendChild(document.createElement('a'));
        let link = document.createTextNode(l);
        a.appendChild(link);
        a.href = l;
    }
    var button = document.createElement('button');
    button.innerHTML = '[X]';
    var button2 = document.createElement('input');
    button2.type = 'button'
    button2.value = 'Dodaj do słów kluczowych';
    button2.onclick = appendtoinput;
    li.appendChild(button);
    li.appendChild(button2);
    ul.appendChild(li);
}

function removesuple (event) {
    if(event.target.type == 'submit') {
        let z = event.target.parentElement.firstChild.data;
        let itemsRetrieved = JSON.parse(localStorage.getItem('suple'));
        itemsRetrieved.splice(itemsRetrieved.indexOf(z), 1);
        localStorage.setItem('suple', JSON.stringify(itemsRetrieved));
        event.target.parentElement.remove();
    }
}

function removeodzywki (event) {
    if(event.target.type == 'submit') {
        let z = event.target.parentElement.firstChild.data;
        let itemsRetrieved = JSON.parse(localStorage.getItem('odzywki'));
        itemsRetrieved.splice(itemsRetrieved.indexOf(z), 1);
        localStorage.setItem('odzywki', JSON.stringify(itemsRetrieved));
        event.target.parentElement.remove();
    }
}
function getInputValue() {
    // Selecting the input element and get its value 
    var inputVal = document.getElementById("slowolista").value;
    
    // Displaying the value
    return inputVal;
}

function getLinkInputValue() {
    // Selecting the input element and get its value 
    var inputVal = document.getElementById("link").value;
    
    // Displaying the value
    return inputVal;
}

function appendtoinput(event) {
    var z = event.target.parentElement.firstChild.data;
    var z = z.replace(", ","")
    var l = event.target.parentElement.firstElementChild.href;
    var i = document.getElementById('slowaklucz');

    i.value += `${z}, ${l}; `;
}