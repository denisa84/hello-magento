if (!window.Polar) {
    window.Polar = {};
}

Polar.toggle = function(trigger, target) {
    function getGroup(elem) {
        return target.classNames().filter(function(name) {
            return name.startsWith("polar-group");
        })[0];
    }

    function hideGroupMembers(groupName) {
        var triggers = [];
        var targets = [];
        $$("." + groupName).each(function(elem) {
            // Dont do anything with the clicked trigger/target
            if (elem == target || elem == trigger) {
                return;
            }
            if (elem.hasClassName("polar-toggle-trigger")) {
                triggers.push(elem);
            } else {
                targets.push(elem);
            }
        });

        var max = Math.min(triggers.length, targets.length);
        for (var i = 0; i < max; i++) {
            hide(triggers[i], targets[i]);
        }
    }

    function hide(trigger, target) {
        if (trigger.hasClassName("expanded")) {
            trigger.removeClassName("expanded");
            trigger.addClassName("collapsed");
        }

        if (target.hasClassName("hide-for-small") || target.getStyle("display") === "none") {
            return;
        }

        Effect.BlindUp(target, {duration: 0.1});
    }

    function show(trigger, target) {
        if (target.hasClassName("hide-for-small")) {
            target.setStyle({
                display: "none"
            });
            target.removeClassName("hide-for-small");
        }

        if (trigger.hasClassName("collapsed")) {
            trigger.removeClassName("collapsed");
            trigger.addClassName("expanded");
        }

        Effect.BlindDown(target, {duration: 0.1});
    }

    return function(evt) {
      evt.stop()
        var groupName = getGroup(target);
        if (groupName) {
            hideGroupMembers(groupName);
        }

        setTimeout(function() {
            if (target.getStyle("display") === "none") {
                show(trigger, target);
            } else {
                hide(trigger, target);
            }
        }, 150);


    }
};

Polar.Nav = {
    initialize: function (container) {
        if (!$(container)) {
            return
        }

        $(container).childElements().each(function(el,ind){
            this.handleNav(el);
        }.bind(this));
    },

    handleNav: function(element) {
        if (element.down('ul') != undefined) {
            element.down('a').observe('click',Polar.toggle(element.down('a'),element.down('ul')));
            element.down('ul').addClassName("hide-for-small");
            element.down('ul').childElements().each(function(el,ind) {
                this.handleNav(el)
            }.bind(this));
        }
    }
};


document.observe("dom:loaded", function() {
    var triggers = $$(".polar-toggle-trigger").toArray();
    var targets = $$(".polar-toggle").toArray();
    var max = Math.min(triggers.length, targets.length);

    for (var i = 0; i < max; i++) {
        var trigger = triggers[i];
        var target = targets[i];
        trigger.observe("click", Polar.toggle(trigger, target));
    }

    Polar.Nav.initialize('nav-mobile');
});

Polar.TopCart= {
    initialize: function (container,element) {
        if(!$(container)) {
            return
        }

        this.container = $(container);
        this.onElementMouseClick = this.handleMouseClick.bindAsEventListener(this);

        $$(element).each(function(el){
            el.observe('click', this.onElementMouseClick)
            if (!this.element) {
              this.element = el
            }
        }.bind(this))
    },

    handleMouseClick: function (evt) {
        evt.stop()
        if (!this.element.hasClassName('expanded'))  {
            if (!$(this.container.id).hasClassName('process')) {
                this.showCart();
            }
        }
        else {
            this.hideCart();
        }
    },
    showCart: function (timePeriod) {
        //this.container.parentNode.style.zIndex=992;
        new Effect.SlideDown(this.container.id, { duration: 0.1,
            beforeStart: function(effect) {$( effect.element.id ).addClassName('process');},
            afterFinish: function(effect) {$( effect.element.id ).removeClassName('process'); }
            });
        this.element.addClassName('expanded');
        if(timePeriod) {
            this.timePeriod = timePeriod*1000;
            this.interval = setTimeout(this.hideCart.bind(this), this.timePeriod);
        }
    },
    
    hideCart: function () {

        if (!$(this.container.id).hasClassName('process') && this.element.hasClassName('expanded')) {
            new Effect.SlideUp(this.container.id, { duration: 0.1,
                beforeStart: function(effect) {$( effect.element.id ).addClassName('process');},
                afterFinish: function(effect) {
                    $( effect.element.id ).removeClassName('process');
                    effect.element.parentNode.style.zIndex=1;
                    }
                });
        }
        if (this.interval !== null) {
            clearTimeout(this.interval);
            this.interval = null;
        }
        this.element.removeClassName('expanded');
    }
    
};
window.onkeyup = function (event) {
  if (event.keyCode == 27) { // When the escape-button is released …
    Polar.TopCart.hideCart(); // … top-cart is hidden
  }
}
