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
        li.appendChild(document.createTextNode(item));
        var button = document.createElement('button');
        button.innerHTML = '[X]';
        li.appendChild(button);
        ul.appendChild(li);
    });
    // odzywki

    var itemsRetrieved = JSON.parse(localStorage.getItem('odzywki'));
    var ul = document.getElementById("odzywki");
    itemsRetrieved.forEach(item => {
        var li = document.createElement("li");
        li.appendChild(document.createTextNode(item));
        var button = document.createElement('button');
        button.innerHTML = '[X]';
        li.appendChild(button);
        ul.appendChild(li);
    });

}
function addwordsuple() {
    var z = getInputValue();
    let itemsRetrieved = JSON.parse(localStorage.getItem('suple'));
    itemsRetrieved.push(z);
    localStorage.setItem('suple', JSON.stringify(itemsRetrieved));
    var ul = document.getElementById("suple");
    var li = document.createElement("li");
    li.appendChild(document.createTextNode(z));
    var button = document.createElement('button');
    button.innerHTML = '[X]';
    li.appendChild(button);
    ul.appendChild(li);
   
}

function addwordodzywki() {
    var z = getInputValue();
    let itemsRetrieved = JSON.parse(localStorage.getItem('odzywki'));
    itemsRetrieved.push(z);
    localStorage.setItem('odzywki', JSON.stringify(itemsRetrieved));
    var ul = document.getElementById("odzywki");
    var li = document.createElement("li");
    li.appendChild(document.createTextNode(z));
    var button = document.createElement('button');
    button.innerHTML = '[X]';
    li.appendChild(button);
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
