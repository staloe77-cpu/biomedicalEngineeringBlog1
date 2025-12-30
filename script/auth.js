const Auth = {

    supportError: function(message, xhr = null) {
        console.error("AUTH ERROR:", message, xhr);
		
		$('#globalErrorBox')
            .html(
                `<strong>${message}</strong><br>
                 Jeśli problem się powtarza — skontaktuj się z administracją:
                 <a href="mailto:admin@system.local">admin@system.local</a>`
            )
            .show();
    },

    checkAuth: function() {
        $.ajax({
            url: 'api/auth.php',
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if (response.loggedIn) {
                    Auth.showLoggedIn(response.user);
                } else {
                    Auth.showLoggedOut();
                    if (window.location.pathname.endsWith('posts.html')) {
                        window.location.href = 'index.html';
                    }
                }
            },
            error: function(xhr) {
                Auth.showLoggedOut();
				
				Auth.supportError(
                    "Błąd podczas sprawdzania sesji użytkownika.",
                    xhr
                );

                if (window.location.pathname.endsWith('posts.html')) {
                    window.location.href = 'index.html';
                }
            }
        });
    },

    showLoggedIn: function(user) {
        $('.user-info').text('Witaj, ' + user.username).show();
        $('.btn-logout').show();
        $('.btn-login').hide();
        $('.nav-item-posts').show();
    },

    showLoggedOut: function() {
        $('.user-info').hide();
        $('.btn-logout').hide();
        $('.btn-login').show();
        $('.nav-item-posts').hide();
    },

    showAuthModal: function(tab = 'login') {
        $('#authModal').show();
        $('.auth-tab').removeClass('active');
        $(`.auth-tab[data-tab="${tab}"]`).addClass('active');

        if (tab === 'login') {
            $('#loginForm').show();
            $('#registerForm').hide();
        } else {
            $('#loginForm').hide();
            $('#registerForm').show();
        }
    },

    hideAuthModal: function() {
        $('#authModal').hide();
        $('#loginForm')[0].reset();
        $('#registerForm')[0].reset();
        $('.auth-message').hide().removeClass('success error');
    },

    login: function(username, password) {
        $.ajax({
            url: 'api/auth.php',
            method: 'POST',
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            data: JSON.stringify({
                action: 'login',
                username: username,
                password: password
            }),
            success: function(response) {
                Auth.hideAuthModal();
                Auth.showLoggedIn(response.user);

                if (
                    window.location.pathname.endsWith('index.html') ||
                    window.location.pathname === '/' ||
                    window.location.pathname.endsWith('/')
                ) {
                    Auth.loadPublishedPosts();
                } else {
                    window.location.href = 'posts.html';
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText || "{}");

                $('#loginMessage')
                    .text(response.error || "Nie udało się zalogować.")
                    .addClass('error')
                    .show();
					
					Auth.supportError(
                    "Wystąpił błąd podczas logowania użytkownika.",
                    xhr
                );
            }
        });
    },

    register: function(username, password) {
        $.ajax({
            url: 'api/auth.php',
            method: 'POST',
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            data: JSON.stringify({
                action: 'register',
                username: username,
                password: password
            }),
            success: function(response) {
                Auth.hideAuthModal();
                Auth.showLoggedIn(response.user);

                if (
                    window.location.pathname.endsWith('index.html') ||
                    window.location.pathname === '/' ||
                    window.location.pathname.endsWith('/')
                ) {
                    Auth.loadPublishedPosts();
                } else {
                    window.location.href = 'posts.html';
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText || "{}");

                $('#registerMessage')
                    .text(response.error || "Rejestracja nie powiodła się.")
                    .addClass('error')
                    .show();

                Auth.supportError(
                    "Wystąpił błąd podczas rejestracji użytkownika.",
                    xhr
                );
            }
        });
    },

    logout: function() {
        $.ajax({
            url: 'api/auth.php',
            method: 'DELETE',
            xhrFields: { withCredentials: true },
            success: function() {
                Auth.showLoggedOut();

                if (
                    window.location.pathname.endsWith('index.html') ||
                    window.location.pathname === '/' ||
                    window.location.pathname.endsWith('/')
                ) {
                    Auth.loadPublishedPosts();
                } else {
                    window.location.href = 'index.html';
                }
            },
            error: function(xhr) {
                Auth.supportError(
                    "Nie udało się poprawnie wylogować użytkownika.",
                    xhr
                );
            }
        });
    },

    loadPublishedPosts: function() {
        $.ajax({
            url: 'api/posts.php',
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(posts) {

if (posts.length === 0) {
                    $('#postsContainer').html('<p>Brak opublikowanych postów.</p>');
                } else {
                    let html = '';
                    posts.forEach(function(post) {
                        html += '<div class="post-card" data-post-id="' + post.id + '">';
                        html += '<h3>' + (post.title || 'Bez tytułu') + '</h3>';

                        html += '<div class="post-meta">';
                        if (post.author) html += 'Autor: ' + post.author + ' | ';
                        if (post.created_at) {
                            html += 'Data: ' + new Date(post.created_at).toLocaleDateString('pl-PL');
                        }
                        html += '</div>';

                        if (post.image) {
                            html += '<img class="post-image" src="' + post.image + '">';
                        }

                        if (post.content) {
                            html += '<div class="post-content">' + post.content + '</div>';
                        }

                        html += '<div class="comments-section" data-post-id="' + post.id + '">';
                        html += '<h4 class="comments-title">Komentarze</h4>';
                        html += '<div class="comments-list" id="comments-' + post.id + '"></div>';
                        html += '<div class="comment-form-container" id="comment-form-' + post.id + '"></div>';
                        html += '</div>';
                        html += '</div>';
                    });

                    $('#postsContainer').html(html);

                    posts.forEach(function(post) {
                        Comments.loadComments(post.id);
                    });
                }

                $('#postsSection').show();
            },
            error: function(xhr) {
                $('#postsContainer').html(
                    '<p class="auth-message error">Błąd podczas ładowania postów.</p>'
                );

                Auth.supportError(
                    "Wystąpił błąd podczas pobierania listy postów.",
                    xhr
                );
            }
        });
    },

    loadPosts: function() {
        $.ajax({
            url: 'api/posts.php?my=true',
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(posts) {
                PostManager.displayPosts(posts);
            },
            error: function(xhr) {
                $('#postsContainer').html(
                    '<p class="auth-message error">Błąd podczas ładowania postów.</p>'
                );

                Auth.supportError(
                    "Wystąpił błąd podczas pobierania postów użytkownika.",
                    xhr
                );
            }
        });
    }
};

$(document).ready(function() {
	
	Auth.checkAuth();

    if (
        window.location.pathname.endsWith('index.html') ||
        window.location.pathname === '/' ||
        window.location.pathname.endsWith('/')
    ) {
        Auth.loadPublishedPosts();
    }

    if (window.location.pathname.endsWith('posts.html')) {
        Auth.loadPosts();
    }

    $('.auth-tab').click(function() {
        const tab = $(this).data('tab');
        Auth.showAuthModal(tab);
    });

    $('.auth-close, .auth-modal').click(function(e) {
        if (e.target === this) Auth.hideAuthModal();
    });

    $('.btn-login').click(function() {
        Auth.showAuthModal('login');
    });

    $('.btn-logout').click(function() {
        Auth.logout();
    });

    $('#loginForm').submit(function(e) {
        e.preventDefault();
        Auth.login(
            $('#loginUsername').val(),
            $('#loginPassword').val()
        );
    });

    $('#registerForm').submit(function(e) {
        e.preventDefault();
        Auth.register(
            $('#registerUsername').val(),
            $('#registerPassword').val()
        );
    });

});