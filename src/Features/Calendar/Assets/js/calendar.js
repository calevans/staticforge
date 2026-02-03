class StaticForgeCalendar {
    constructor(elementId, events) {
        this.container = document.getElementById(elementId);
        if (!this.container) {
            console.error(`StaticForgeCalendar: Element with ID '${elementId}' not found.`);
            return;
        }
        
        // Parse dates in events
        this.events = (events || []).map(e => ({
            ...e,
            start: new Date(e.start),
            end: e.end ? new Date(e.end) : new Date(e.start)
        }));

        this.currentDate = new Date();
        this.currentView = 'month'; // 'month', 'week', 'year'
        
        this.monthNames = ["January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];
        
        this.dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

        this.init();
    }

    init() {
        this.renderStructure();
        this.render();
        this.attachGlobalListeners();
    }

    renderStructure() {
        this.container.innerHTML = `
            <div class="sf-calendar-container">
                <div class="sf-calendar-header">
                    <div class="sf-calendar-controls">
                        <button class="sf-calendar-btn" data-action="prev">&lt;</button>
                        <button class="sf-calendar-btn" data-action="today">Today</button>
                        <button class="sf-calendar-btn" data-action="next">&gt;</button>
                    </div>
                    <h2 class="sf-calendar-title" id="${this.container.id}-title"></h2>
                    <div class="sf-calendar-controls">
                        <button class="sf-calendar-btn active" data-view="month">Month</button>
                        <button class="sf-calendar-btn" data-view="week">Week</button>
                        <button class="sf-calendar-btn" data-view="year">Year</button>
                    </div>
                </div>
                <div id="${this.container.id}-view-container" class="sf-calendar-view-container"></div>
            </div>
            <div id="${this.container.id}-modal" class="sf-modal-overlay">
                <div class="sf-modal-content">
                    <button class="sf-modal-close">&times;</button>
                    <div class="sf-modal-body" id="${this.container.id}-modal-body"></div>
                </div>
            </div>
        `;

        // Bind navigation buttons
        this.container.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                this.handleNavigation(action);
            });
        });

        // Bind view buttons
        this.container.querySelectorAll('[data-view]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.target.dataset.view;
                this.changeView(view);
                
                // Update active state
                this.container.querySelectorAll('[data-view]').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
            });
        });

        // Modal close
        const modal = document.getElementById(`${this.container.id}-modal`);
        const closeBtn = modal.querySelector('.sf-modal-close');
        closeBtn.addEventListener('click', () => this.closeModal());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.closeModal();
        });
    }

    handleNavigation(action) {
        if (action === 'today') {
            this.currentDate = new Date();
        } else if (action === 'prev') {
            this.shiftDate(-1);
        } else if (action === 'next') {
            this.shiftDate(1);
        }
        this.render();
    }

    shiftDate(direction) {
        if (this.currentView === 'month') {
            this.currentDate.setMonth(this.currentDate.getMonth() + direction);
        } else if (this.currentView === 'week') {
            this.currentDate.setDate(this.currentDate.getDate() + (direction * 7));
        } else if (this.currentView === 'year') {
            this.currentDate.setFullYear(this.currentDate.getFullYear() + direction);
        }
    }

    changeView(view) {
        this.currentView = view;
        this.render();
    }

    render() {
        const titleEl = document.getElementById(`${this.container.id}-title`);
        const viewContainer = document.getElementById(`${this.container.id}-view-container`);
        viewContainer.innerHTML = '';

        if (this.currentView === 'month') {
            titleEl.textContent = `${this.monthNames[this.currentDate.getMonth()]} ${this.currentDate.getFullYear()}`;
            this.renderMonthView(viewContainer);
        } else if (this.currentView === 'week') {
            const startOfWeek = this.getStartOfWeek(this.currentDate);
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(endOfWeek.getDate() + 6);
            
            // Format title differently if week spans months
            if (startOfWeek.getMonth() !== endOfWeek.getMonth()) {
                titleEl.textContent = `${this.monthNames[startOfWeek.getMonth()]} ${startOfWeek.getDate()} - ${this.monthNames[endOfWeek.getMonth()]} ${endOfWeek.getDate()}, ${endOfWeek.getFullYear()}`;
            } else {
                titleEl.textContent = `${this.monthNames[startOfWeek.getMonth()]} ${startOfWeek.getDate()} - ${endOfWeek.getDate()}, ${endOfWeek.getFullYear()}`;
            }
            this.renderWeekView(viewContainer);
        } else if (this.currentView === 'year') {
            titleEl.textContent = `${this.currentDate.getFullYear()}`;
            this.renderYearView(viewContainer);
        }
    }

    renderMonthView(container) {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        const firstDayOfMonth = new Date(year, month, 1);
        const lastDayOfMonth = new Date(year, month + 1, 0);
        
        const startDay = firstDayOfMonth.getDay(); // 0 = Sun
        const daysInMonth = lastDayOfMonth.getDate();
        
        // Previous month filler days
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        
        let html = '<div class="sf-calendar-month-grid">';
        
        // Headers
        this.dayNames.forEach(day => {
            html += `<div class="sf-calendar-day-header">${day}</div>`;
        });

        // Days
        let dayCount = 1;
        let nextMonthDayCount = 1;

        // 6 rows (42 cells) to cover all possibilities
        for (let i = 0; i < 42; i++) {
            if (i < startDay) {
                // Previous month
                const dayNum = prevMonthLastDay - (startDay - 1 - i);
                html += `<div class="sf-calendar-day other-month"><span class="sf-calendar-day-number">${dayNum}</span></div>`;
            } else if (dayCount > daysInMonth) {
                // Next month
                html += `<div class="sf-calendar-day other-month"><span class="sf-calendar-day-number">${nextMonthDayCount++}</span></div>`;
            } else {
                // Current month
                const currentDayDate = new Date(year, month, dayCount);
                const isToday = this.isSameDate(currentDayDate, new Date());
                const events = this.getEventsForDate(currentDayDate);
                
                let eventsHtml = '';
                events.forEach((evt, idx) => {
                   eventsHtml += `<div class="sf-event-item" data-event-index="${this.events.indexOf(evt)}">${evt.title}</div>`;
                });

                html += `
                    <div class="sf-calendar-day ${isToday ? 'today' : ''}">
                        <span class="sf-calendar-day-number">${dayCount}</span>
                        ${eventsHtml}
                    </div>
                `;
                dayCount++;
            }
        }
        
        html += '</div>';
        container.innerHTML = html;
    }

    renderWeekView(container) {
        const startOfWeek = this.getStartOfWeek(this.currentDate);
        let html = '<div class="sf-calendar-week-list">';
        
        for (let i = 0; i < 7; i++) {
            const currentDay = new Date(startOfWeek);
            currentDay.setDate(startOfWeek.getDate() + i);
            
            const events = this.getEventsForDate(currentDay);
            const isToday = this.isSameDate(currentDay, new Date());
            
            let eventsHtml = '';
            if (events.length === 0) {
                eventsHtml = '<div style="color:#999; font-style:italic; padding:5px;">No events</div>';
            } else {
                events.forEach(evt => {
                     eventsHtml += `
                        <div class="sf-event-item" style="margin-bottom:5px; padding:8px;" data-event-index="${this.events.indexOf(evt)}">
                            <strong>${this.formatTime(evt.start)}</strong> ${evt.title}
                        </div>
                     `;
                });
            }

            html += `
                <div class="sf-week-day-row ${isToday ? 'today' : ''}" style="${isToday ? 'background:#f0f7ff;' : ''}">
                    <div class="sf-week-day-header">
                        ${this.dayNames[currentDay.getDay()]}, ${this.monthNames[currentDay.getMonth()]} ${currentDay.getDate()}
                    </div>
                    <div>${eventsHtml}</div>
                </div>
            `;
        }
        
        html += '</div>';
        container.innerHTML = html;
    }

    renderYearView(container) {
        const year = this.currentDate.getFullYear();
        let html = '<div class="sf-calendar-year-grid">';
        
        for (let m = 0; m < 12; m++) {
            html += `
                <div class="sf-year-month-card">
                    <div class="sf-year-month-title">${this.monthNames[m]}</div>
                    <div class="sf-mini-grid">
            `;
            
            // Mini grid logic
            const firstDay = new Date(year, m, 1);
            const startDay = firstDay.getDay();
            const daysInMonth = new Date(year, m + 1, 0).getDate();
            
            // Empty slots
            for(let k=0; k<startDay; k++) {
                html += `<div></div>`;
            }
            
            // Days
            for(let d=1; d<=daysInMonth; d++) {
                const date = new Date(year, m, d);
                const hasEvent = this.getEventsForDate(date).length > 0;
                html += `<div class="sf-mini-day ${hasEvent ? 'has-event' : ''}">${d}</div>`;
            }
            
            html += `</div></div>`;
        }
        
        html += '</div>';
        container.innerHTML = html;
    }

    attachGlobalListeners() {
        // Event clicking delegation
        this.container.addEventListener('click', (e) => {
            const eventItem = e.target.closest('.sf-event-item');
            if (eventItem) {
                const index = eventItem.dataset.eventIndex;
                if (index !== undefined) {
                    this.openModal(this.events[index]);
                }
            }
        });
    }

    openModal(event) {
        const modal = document.getElementById(`${this.container.id}-modal`);
        const body = document.getElementById(`${this.container.id}-modal-body`);
        
        let timeString = `${this.formatDate(event.start)} ${this.formatTime(event.start)}`;
        if (event.end) {
            timeString += ` - ${this.formatTime(event.end)}`;
            if (!this.isSameDate(event.start, event.end)) {
                timeString = `${this.formatDate(event.start)} ${this.formatTime(event.start)} - ${this.formatDate(event.end)} ${this.formatTime(event.end)}`;
            }
        }

        body.innerHTML = `
            <h3>${event.title}</h3>
            <div class="sf-event-meta">
                <strong>Time:</strong> ${timeString}<br>
                ${event.location ? `<strong>Location:</strong> ${event.location}<br>` : ''}
            </div>
            <div class="sf-event-description">
                ${event.description || 'No description provided.'}
            </div>
        `;
        
        modal.classList.add('open');
    }

    closeModal() {
        const modal = document.getElementById(`${this.container.id}-modal`);
        modal.classList.remove('open');
    }

    // Helpers
    getEventsForDate(date) {
        return this.events.filter(e => {
            return this.isSameDate(e.start, date);
            // Note: Simplification for single-day events or start-day matching only
        });
    }

    isSameDate(d1, d2) {
        return d1.getFullYear() === d2.getFullYear() &&
               d1.getMonth() === d2.getMonth() &&
               d1.getDate() === d2.getDate();
    }
    
    getStartOfWeek(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day; // Adjust so Sunday is start
        return new Date(d.setDate(diff));
    }

    formatTime(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    formatDate(date) {
        return date.toLocaleDateString();
    }
}
