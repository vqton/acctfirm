from django.urls import path
from . import views
from django.contrib.sitemaps.views import sitemap
from .sitemap import MySitemap

sitemaps = {
    "my_sitemap": MySitemap,
}

urlpatterns = [
    path("", views.home, name="home"),
    path("about-us/", views.about_us, name="about_us"),
    path("services/", views.services, name="services"),
    path("contact/", views.contact_view, name="contact"),
    path("success/", views.success_view, name="success"),
    path(
        "sitemap.xml",
        sitemap,
        {"sitemaps": sitemaps},
        name="django.contrib.sitemaps.views.sitemap",
    ),
]
