(function() {
    const habitList = document.getElementById('habitList');
    const addHabitBtn = document.getElementById('addHabitBtn');
    const habitModal = document.getElementById('habitModal');
    const habitForm = document.getElementById('habitForm');
    const habitName = document.getElementById('habitName');
    const habitDescription = document.getElementById('habitDescription');
    const habitIdField = document.getElementById('habitId');
    const modalTitle = document.getElementById('habitModalTitle');
    const closeModalBtn = document.getElementById('closeHabitModal');
    const progressFill = document.querySelector('.progress-fill');
    const progressText = document.querySelector('.progress-text');

    function toggleModal(show) {
        habitModal.hidden = !show;
    }

    function openHabitModal(data) {
        modalTitle.textContent = data ? 'Edit Habit' : 'Add Habit';
        habitName.value = data ? data.name : '';
        habitDescription.value = data ? data.description : '';
        habitIdField.value = data ? data.id : '';
        toggleModal(true);
    }

    function updateProgress(summary) {
        if (!summary) return;
        progressFill.style.width = `${summary.percentage}%`;
        progressText.textContent = `${summary.completed}/${Math.max(summary.total, 1)} completed — ${summary.percentage}%`;
        if (summary.percentage >= 90) {
            progressFill.classList.add('success');
            progressText.classList.add('success');
        } else {
            progressFill.classList.remove('success');
            progressText.classList.remove('success');
        }
    }

    function handleReward(reward) {
        if (reward && reward.awarded) {
            alert(`New collectible unlocked: ${reward.filename}`);
            window.location.reload();
        } else if (reward && reward.message === 'Collection complete!') {
            alert(reward.message);
        }
    }

    function submitHabit(event) {
        event.preventDefault();
        const payload = {
            name: habitName.value.trim(),
            description: habitDescription.value.trim(),
            csrf_token: csrfToken
        };
        const id = habitIdField.value;
        const method = id ? 'PUT' : 'POST';
        if (id) payload.id = id;

        fetch('/api/habits.php', {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        }).then(res => res.json())
          .then(() => window.location.reload());
    }

    function deleteHabit(id) {
        if (!confirm('Delete this habit?')) return;
        fetch('/api/habits.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ id, csrf_token: csrfToken })
        }).then(() => window.location.reload());
    }

    function reorderHabit(id, direction) {
        fetch('/api/habits.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ id, direction, csrf_token: csrfToken })
        }).then(() => window.location.reload());
    }

    function toggleCompletion(id, completed) {
        fetch('/api/completions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ habit_id: id, completed, csrf_token: csrfToken })
        }).then(res => res.json())
          .then(data => {
              updateProgress(data.summary);
              handleReward(data.reward);
          });
    }

    if (habitList) {
        habitList.addEventListener('click', (event) => {
            const item = event.target.closest('.habit-item');
            if (!item) return;
            const id = item.dataset.habitId;
            if (event.target.classList.contains('delete')) {
                deleteHabit(id);
            }
            if (event.target.classList.contains('edit')) {
                const name = item.querySelector('.habit-name').textContent;
                const descriptionEl = item.querySelector('.muted.small');
                openHabitModal({ id, name, description: descriptionEl ? descriptionEl.textContent : '' });
            }
            if (event.target.classList.contains('reorder')) {
                reorderHabit(id, event.target.dataset.direction);
            }
        });

        habitList.addEventListener('change', (event) => {
            if (event.target.matches('input[type="checkbox"][data-habit]')) {
                const id = event.target.dataset.habit;
                toggleCompletion(id, event.target.checked);
            }
        });
    }

    if (addHabitBtn) addHabitBtn.addEventListener('click', () => openHabitModal());
    if (closeModalBtn) closeModalBtn.addEventListener('click', () => toggleModal(false));
    if (habitForm) habitForm.addEventListener('submit', submitHabit);
})();
