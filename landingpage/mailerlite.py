import requests
import json


def send_email(subject, to, content):
    api_key = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI0IiwianRpIjoiOTRiNWNiNWYwMjgzYmQ4NDUyZjA4MmRhYzhjNDU1NTYxYTY3NmJkYzU2ZDlmYmFhYzlkNDlhOTgxZjg2ZGE0NmNmNWIxZDc3Yzc3ZGM0NDgiLCJpYXQiOjE2NzYwMDAzNTIuOTk4NTE4LCJuYmYiOjE2NzYwMDAzNTIuOTk4NTIxLCJleHAiOjQ4MzE2NzM5NTIuOTk0ODQ0LCJzdWIiOiIzNDk1NDAiLCJzY29wZXMiOltdfQ.n1YyZwl7FyEkctUV3QSs2H6wm-YvS7-7d5XVKJYp-BCDogkCdgWmWF6DW8d_a1qNvGQJQQVT0krOPlz5U3DdTMHX7pghWbJN4YPnhk9fbQnuZy73PsBBV2GkhKEAJue2KUv0QH65CLuPfgxeFGq1u98W1ldtzRc6Pd-P1YEkCg0DqI50KKjXmcKkOVwLZZ_DUm1Kza2aN1HYCxJfQlBzGadFeymV_cbIEx2ksLy6dMA_ggfwnlk_NzZJ8LrsXlbAEkCebLDFrdMRlnsDfH-bYxL8RHfpwfVybB-22wwznCZOsiSHWueSzgoO-YVs_Uf0ePz269ZLmuErOA8t5uDHPndJ6KfuOaLbc6NHjKkau1KOrXh9npirdy2NIWrJQ0nE4LtWI3sz1pYh_iWUEMiZHh-phslaQfI_R31SFonwl0Sakgrf6rlqiWLgwe9YIIpRzm0Ral4OxI3u_HAUnR-H1_sQ4jvt-kkgyr8m1hkECdUKxPMys8rnaIOIYFOSLd7CEE8IiCJ_-HNs1svoev2Ktg3WmrYaEFmZ8qOZevOn_xZs3YfNBqw4RMrzYoMdzbkkIUuW8k8YpvmLrMsz9XQTKqzB_1---yICFXK6mFKkTCE3Cgnxt7uwROWiOIUm_IAnZdMlK2D5izfYWEMk13HXf4Bmr_JxMr_kXMCt0UiUF-A"
    headers = {
        "Authorization": "apikey " + api_key,
        "Content-Type": "application/json",
    }
    # data = {"subject": subject, "to": [{"email": to}], "html": content.encode("utf-8")}
    data = {"subject": subject, "to": [{"email": to}], "html": content}
    response = requests.post(
        "https://connect.mailerlite.com",
        headers=headers,
        data=json.dumps(data, ensure_ascii=False).encode("utf-8"),
    )

    with open("response.txt", "w") as file:
        file.write(response.text)

    if response.status_code == 200:

        return True
    else:
        return False
