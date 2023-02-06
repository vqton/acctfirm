# Create your models here.
from django.db import models
from django.contrib.auth.models import User
from django.utils.text import slugify
from ckeditor.fields import RichTextField


class Carousel(models.Model):
    image = models.ImageField(upload_to="carousel/")
    caption = models.CharField(max_length=255)
    link = models.URLField(blank=True, null=True)
    is_active = models.BooleanField(default=True)
    order = models.PositiveIntegerField()

    def __str__(self):
        return self.caption

    class Meta:
        ordering = ["order"]


class Menu(models.Model):
    name = models.CharField(max_length=255)
    link = models.URLField()
    is_active = models.BooleanField(default=True)
    order = models.PositiveIntegerField()

    def __str__(self):
        return self.name

    class Meta:
        ordering = ["order"]


class Header(models.Model):
    logo = models.ImageField(upload_to="header/logos/")
    menu = models.ManyToManyField(Menu, blank=True)
    heading = models.CharField(max_length=255)
    subheading = models.CharField(max_length=255, blank=True, null=True)
    background_color = models.CharField(max_length=255, default="#000000")
    text_color = models.CharField(max_length=255, default="#ffffff")

    def __str__(self):
        return self.heading


class Feature(models.Model):
    name = models.CharField(max_length=255)
    description = models.TextField()
    created_by = models.ForeignKey(User, on_delete=models.CASCADE)
    created_at = models.DateTimeField(auto_now_add=True)
    is_published = models.BooleanField(default=False)
    slug = models.SlugField(unique=True, blank=True)

    def save(self, *args, **kwargs):
        self.slug = slugify(self.name)
        super().save(*args, **kwargs)

    def __str__(self):
        return self.name


class Service(models.Model):
    name = models.CharField(max_length=255)
    description = models.TextField()
    slug = models.SlugField(unique=True, blank=True)
    created_by = models.ForeignKey(
        User, on_delete=models.SET_NULL, null=True, related_name="services_created"
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_by = models.ForeignKey(
        User, on_delete=models.SET_NULL, null=True, related_name="services_updated"
    )
    updated_at = models.DateTimeField(auto_now=True)
    is_published = models.BooleanField(default=False)

    def save(self, *args, **kwargs):
        self.slug = slugify(self.name)
        super().save(*args, **kwargs)

    def __str__(self):
        return self.name


class Contact(models.Model):
    name = models.CharField(max_length=255)
    email = models.EmailField()
    subject = models.CharField(max_length=255)
    message = models.TextField()
    received_at = models.DateTimeField(auto_now_add=True)
    processed_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        blank=True,
        null=True,
        related_name="processed_contacts",
    )

    def __str__(self):
        return self.name


class SEO(models.Model):
    meta_title = models.CharField(max_length=255)
    meta_description = models.TextField(blank=True)
    meta_keywords = models.CharField(max_length=255, blank=True)
    meta_robots = models.CharField(max_length=255, default="index,follow")

    def __str__(self):
        return self.meta_title


class Testimonial(models.Model):
    user = models.ForeignKey(
        User, on_delete=models.CASCADE, related_name="testimonials"
    )
    name = models.CharField(max_length=255)
    message = models.TextField()
    rating = models.IntegerField()
    is_published = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    def __str__(self):
        return self.name


class Footer(models.Model):
    copyright = models.CharField(max_length=255)
    company_name = models.CharField(max_length=255)
    company_address = models.CharField(max_length=255)
    company_email = models.EmailField()
    company_phone = models.CharField(max_length=20)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    is_published = models.BooleanField(default=True)

    def __str__(self):
        return self.copyright


class Image(models.Model):
    image = models.ImageField(upload_to="images/")
    name = models.CharField(max_length=100)
    description = models.TextField()


class AboutUs(models.Model):
    user = models.ForeignKey(User, on_delete=models.CASCADE)
    title = models.CharField(max_length=100)
    description = RichTextField(blank=True)
    slug = models.SlugField(max_length=100, unique=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    def __str__(self):
        return self.title

    def save(self, *args, **kwargs):
        self.slug = slugify(self.title)
        super().save(*args, **kwargs)

    class Meta:
        verbose_name_plural = "About Us"
