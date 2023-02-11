from django.contrib.sitemaps import Sitemap
from django.urls import reverse
from .models import BlogPost


class BlogPostSitemap(Sitemap):
    def items(self):
        return BlogPost.objects.all()

    def location(self, obj):
        return reverse("blog_detail", args=[str(obj.slug)])
