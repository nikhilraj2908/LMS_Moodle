document.addEventListener("DOMContentLoaded", function () {
  console.log("OpenRouter Integration Ready ✅");

  const interval = setInterval(() => {
    const titleInput = document.querySelector("#id_fullname");
    const editor = tinymce?.get("id_summary_editor");

    if (!titleInput || !editor) {
      console.warn("Waiting for form fields...");
      return;
    }

    clearInterval(interval);

    const generateBtn = document.createElement("button");
    generateBtn.innerText = "Generate Description";
    generateBtn.style.marginLeft = "10px";
    generateBtn.style.padding = "6px 12px";
    generateBtn.style.background = "#0073e6";
    generateBtn.style.color = "white";
    generateBtn.style.border = "none";
    generateBtn.style.borderRadius = "4px";

    titleInput.parentNode.appendChild(generateBtn);

    generateBtn.addEventListener("click", async (e) => {
      e.preventDefault();

      const courseTitle = titleInput.value.trim();
      if (!courseTitle) {
        alert("Please enter a course title first.");
        return;
      }

      generateBtn.innerText = "Generating... ⏳";

      try {
        const response = await fetch("https://openrouter.ai/api/v1/chat/completions", {
          method: "POST",
          headers: {
            "Authorization": "Bearer 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",
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            model: "mistralai/mistral-7b-instruct",
            messages: [
              {
                role: "user",
                content: `Write a short and professional course description for a course titled: "${courseTitle}"`
              }
            ]
          })
        });

        const data = await response.json();
        const message = data?.choices?.[0]?.message?.content || "No description returned.";

        editor.setContent(message);
      } catch (err) {
        console.error("OpenRouter error:", err);
        alert("Failed to generate course description.");
      }

      generateBtn.innerText = "Generate Description";
    });
  }, 500);
});
