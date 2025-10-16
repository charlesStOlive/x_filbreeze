/**
 * Animations Component pour Front App
 * Intersection Observer et animations d'apparition
 */

class FrontAppAnimations {
    constructor() {
        this.observers = new Map();
        this.init();
    }

    init() {
        this.setupIntersectionObserver();
        this.setupParallaxEffects();
        this.setupAnimations();
        console.log('✨ Animations component initialized');
    }

    setupIntersectionObserver() {
        // Options pour l'observer principal
        const mainObserverOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const mainObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Marquer l'élément comme "en cours de traitement" pour éviter les doubles animations
                    if (entry.target.hasAttribute('data-animation-processing')) {
                        return;
                    }
                    entry.target.setAttribute('data-animation-processing', 'true');
                    
                    // Vérifier si cet élément fait partie d'un groupe d'animation
                    const parent = entry.target.closest('.grid, .flex, .space-y-8, .space-y-6, .space-y-4');
                    const isPartOfGroup = parent && parent.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right').length > 1;
                    
                    if (isPartOfGroup) {
                        // Si c'est un groupe, traiter tout le groupe ensemble
                        this.animateGroup(parent);
                    } else {
                        // Sinon, animer individuellement avec son délai
                        const customDelay = entry.target.getAttribute('data-animation-delay');
                        const delay = customDelay ? parseInt(customDelay) : 0;
                        
                        setTimeout(() => {
                            entry.target.classList.add('visible');
                        }, delay);
                    }
                    
                    // Une fois animé, on arrête d'observer cet élément
                    mainObserver.unobserve(entry.target);
                }
            });
        }, mainObserverOptions);

        // Observer tous les éléments avec animation
        const animatedElements = document.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right');
        animatedElements.forEach(el => {
            mainObserver.observe(el);
        });

        this.observers.set('main', mainObserver);
    }

    animateGroup(parent) {
        const siblings = parent.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right');
        if (siblings.length <= 1) return;

        // Marquer tous les éléments du groupe comme en cours de traitement
        siblings.forEach(sibling => {
            sibling.setAttribute('data-animation-processing', 'true');
        });

        siblings.forEach((sibling, index) => {
            if (!sibling.classList.contains('visible')) {
                // Prioriser le délai personnalisé, sinon utiliser le délai en cascade
                const customDelay = sibling.getAttribute('data-animation-delay');
                const cascadeDelay = index * 150;
                const finalDelay = customDelay ? parseInt(customDelay) : cascadeDelay;
                
                setTimeout(() => {
                    sibling.classList.add('visible');
                    sibling.removeAttribute('data-animation-processing');
                }, finalDelay);
            } else {
                // Si déjà visible, supprimer le marqueur
                sibling.removeAttribute('data-animation-processing');
            }
        });
    }

    setupParallaxEffects() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        if (parallaxElements.length === 0) return;

        const handleParallax = window.utils.throttle(() => {
            const scrolled = window.pageYOffset;
            
            parallaxElements.forEach(element => {
                const speed = parseFloat(element.dataset.parallax) || 0.5;
                const yPos = -(scrolled * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        }, 16);

        window.addEventListener('scroll', handleParallax);
    }

    setupAnimations() {
        // Les délais d'animation sont maintenant gérés directement dans l'Intersection Observer
        // Cette méthode peut être utilisée pour d'autres animations spécifiques si nécessaire
        console.log('✨ Custom animation delays are handled by the Intersection Observer');
    }

    // Méthode publique pour déclencher des animations programmatiques
    triggerAnimation(selector, animationType = 'fade-in-up', delay = 0) {
        const elements = document.querySelectorAll(selector);
        elements.forEach((el, index) => {
            setTimeout(() => {
                el.classList.add(animationType, 'visible');
            }, delay + (index * 100));
        });
    }

    // Méthode pour ajouter un nouveau observer
    addCustomObserver(elements, callback, options = {}) {
        const defaultOptions = {
            threshold: 0.1,
            rootMargin: '0px'
        };

        const observerOptions = { ...defaultOptions, ...options };
        const observer = new IntersectionObserver(callback, observerOptions);

        elements.forEach(el => observer.observe(el));
        
        const observerId = `custom-${this.observers.size}`;
        this.observers.set(observerId, observer);
        
        return observerId;
    }

    // Nettoyer les observers
    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers.clear();
    }
}

// Auto-initialisation et export global
document.addEventListener('DOMContentLoaded', () => {
    window.frontAppAnimations = new FrontAppAnimations();
});

export default FrontAppAnimations;